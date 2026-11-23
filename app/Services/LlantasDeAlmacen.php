<?php

namespace App\Services;

use App\Enums\CondicionLlanta;
use App\Enums\DestinoSalida;
use App\Enums\MotivoRetiroLlanta;
use App\Enums\OrigenLlanta;
use App\Enums\TipoIngreso;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\DetalleIngreso;
use App\Models\DetalleSalida;
use App\Models\IngresoAlmacen;
use App\Models\Llanta;
use App\Models\OrdenTrabajo;
use App\Models\SalidaAlmacen;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Las llantas que se pueden montar en una unidad sin saltarse el almacén.
 *
 * Cada llanta que entra por una recepción recibe su código: la recepción es
 * su lote. Para montarla sale con el vale de una orden de trabajo —el que la
 * orden ya tenga con llantas por montar o, si no hay, uno que se emite en
 * ese momento—, y cada unidad de esa línea se monta una sola vez. Una llanta
 * que se retiró de otra unidad se vuelve a montar sin vale: ya salió del
 * almacén la primera vez, y sigue siendo la misma llanta, con su historial.
 */
class LlantasDeAlmacen
{
    public const CLAVE_CATEGORIA = 'llantas';

    public function __construct(private readonly Kardex $kardex, private readonly RepuestosDeOrden $repuestos) {}

    /**
     * Si el artículo es una llanta: está en la categoría de llantas.
     */
    public function esLlanta(Articulo $articulo): bool
    {
        return $articulo->categoria_articulo_id !== null
            && $articulo->categoria_articulo_id === CategoriaArticulo::query()->where('clave', self::CLAVE_CATEGORIA)->value('id');
    }

    /**
     * Cuántas llantas de esa línea de vale faltan montar: lo que salió, menos
     * lo que se devolvió, menos lo ya montado.
     */
    public function disponibles(DetalleSalida $detalle): int
    {
        return max(0, (int) floor((float) $detalle->cantidad - $detalle->cantidadDevuelta()) - $this->montadasDe($detalle));
    }

    /**
     * Cuántas llantas se montaron (y siguen registradas) con esa línea.
     */
    public function montadasDe(DetalleSalida $detalle): int
    {
        return Llanta::query()->where('detalle_salida_id', $detalle->id)->count();
    }

    /**
     * Las líneas de vale con llantas todavía por montar en la unidad.
     *
     * @return list<array{detalle_salida_id: int, vale: string, fecha: string, articulo_id: int, nombre: string, marca: string|null, disponibles: int}>
     */
    public function lineasPara(Vehiculo $vehiculo): array
    {
        $categoria = CategoriaArticulo::query()->where('clave', self::CLAVE_CATEGORIA)->value('id');

        if ($categoria === null) {
            return [];
        }

        return array_values(DetalleSalida::query()
            ->whereIn('salida_almacen_id', SalidaAlmacen::query()->vigentes()->where('vehiculo_id', $vehiculo->id)->select('id'))
            ->whereIn('articulo_id', Articulo::query()->where('categoria_articulo_id', $categoria)->select('id'))
            ->with(['salida', 'articulo'])
            ->orderBy('id')
            ->get()
            ->map(fn (DetalleSalida $detalle): array => [
                'detalle_salida_id' => $detalle->id,
                'vale' => $detalle->salida->numero(),
                'fecha' => $detalle->salida->fecha->toDateString(),
                'articulo_id' => $detalle->articulo_id,
                'nombre' => $detalle->articulo->nombre,
                'marca' => $detalle->articulo->marca,
                'disponibles' => $this->disponibles($detalle),
            ])
            ->filter(fn (array $linea): bool => $linea['disponibles'] > 0)
            ->all());
    }

    /**
     * Las llantas retiradas que se pueden volver a montar (en esta unidad o
     * en otra). Una retirada por daño no vuelve a rodar.
     *
     * @return list<array{id: int, descripcion: string, placa: string, retirada_el: string, motivo: string}>
     */
    public function retiradas(): array
    {
        return array_values(Llanta::query()
            ->whereNotNull('vehiculo_id')
            ->whereNotNull('retirada_el')
            ->where('motivo_retiro', '!=', MotivoRetiroLlanta::Dano)
            ->with('vehiculo:id,placa')
            ->orderByDesc('retirada_el')
            ->limit(200)
            ->get()
            ->map(fn (Llanta $llanta): array => [
                'id' => $llanta->id,
                'descripcion' => $llanta->descripcion(),
                'placa' => (string) $llanta->vehiculo?->placa,
                'retirada_el' => (string) $llanta->retirada_el?->toDateString(),
                'motivo' => (string) $llanta->motivo_retiro?->label(),
            ])
            ->all());
    }

    /**
     * Las recepciones con llantas: cada una es un lote, con cuántas de sus
     * llantas ya tienen código y dónde está cada una.
     *
     * @return list<array{id: int, numero: string, fecha: string, proveedor: string|null, documento: string|null, lineas: list<array{detalle_ingreso_id: int, articulo: string, marca: string|null, cantidad: int, por_codificar: int, costo: float}>, llantas: list<array{id: int, codigo: string|null, articulo: string|null, situacion: string, donde: string}>}>
     */
    public function lotes(): array
    {
        $lineas = $this->lineasDeIngreso()->with(['ingreso.proveedor', 'articulo'])->get();
        $llantas = Llanta::query()
            ->whereIn('detalle_ingreso_id', $lineas->modelKeys())
            ->with(['vehiculo:id,placa', 'articulo:id,nombre', 'detalleSalida.salida.vehiculo:id,placa'])
            ->orderBy('codigo')
            ->get()
            ->groupBy('detalle_ingreso_id');

        return array_values($lineas
            ->groupBy('ingreso_almacen_id')
            ->sortKeysDesc()
            ->map(function ($delIngreso) use ($llantas): array {
                /** @var IngresoAlmacen $ingreso */
                $ingreso = $delIngreso->first()->ingreso;

                return [
                    'id' => $ingreso->id,
                    'numero' => $ingreso->numero(),
                    'fecha' => $ingreso->fecha->toDateString(),
                    'proveedor' => $ingreso->proveedor?->nombre(),
                    'documento' => $ingreso->comprobante_pendiente ? null : $ingreso->documento(),
                    'lineas' => array_values($delIngreso->map(fn (DetalleIngreso $linea): array => [
                        'detalle_ingreso_id' => $linea->id,
                        'articulo' => $linea->articulo->nombre,
                        'marca' => $linea->articulo->marca,
                        'cantidad' => (int) floor((float) $linea->cantidad_base),
                        'por_codificar' => max(0, (int) floor((float) $linea->cantidad_base) - $llantas->get($linea->id, collect())->count()),
                        'costo' => (float) $linea->costo_unitario,
                    ])->all()),
                    'llantas' => array_values($delIngreso
                        ->flatMap(fn (DetalleIngreso $linea) => $llantas->get($linea->id, collect()))
                        ->map(fn (Llanta $llanta): array => [
                            'id' => $llanta->id,
                            'codigo' => $llanta->codigo,
                            'articulo' => $llanta->articulo?->nombre,
                            ...$this->ubicacion($llanta),
                        ])->all()),
                ];
            })
            ->all());
    }

    /**
     * Cuántas llantas recibidas siguen sin código.
     */
    public function porCodificar(): int
    {
        $lineas = $this->lineasDeIngreso()->get(['id', 'cantidad_base']);
        $codificadas = Llanta::query()->whereIn('detalle_ingreso_id', $lineas->modelKeys())->count();

        return max(0, (int) $lineas->sum(fn (DetalleIngreso $linea): int => (int) floor((float) $linea->cantidad_base)) - $codificadas);
    }

    /**
     * Cuántas llantas de esa línea de recepción faltan codificar.
     */
    public function faltanCodificar(DetalleIngreso $linea): int
    {
        return max(0, (int) floor((float) $linea->cantidad_base) - Llanta::query()->where('detalle_ingreso_id', $linea->id)->count());
    }

    /**
     * Les pone código a las llantas de una línea de recepción: quedan en el
     * almacén, listas para montar.
     *
     * @param  list<string>  $codigos
     * @return list<Llanta>
     */
    public function codificar(DetalleIngreso $linea, array $codigos, CondicionLlanta $condicion, ?string $medida = null): array
    {
        $linea->loadMissing('articulo');

        return array_map(fn (string $codigo): Llanta => Llanta::create([
            'articulo_id' => $linea->articulo_id,
            'detalle_ingreso_id' => $linea->id,
            'origen' => OrigenLlanta::Almacen,
            'marca' => $linea->articulo->marca,
            'medida' => $medida ?? mb_substr($linea->articulo->nombre, 0, 50),
            'codigo' => $codigo,
            'condicion' => $condicion,
            'costo' => $linea->costo_unitario,
        ]), $codigos);
    }

    /**
     * Las llantas con código que se pueden montar en la unidad: las que
     * están en el almacén y las que ya se le entregaron con un vale.
     *
     * @return list<array{id: int, codigo: string|null, descripcion: string, lote: string|null, vale: string|null}>
     */
    public function montablesEn(Vehiculo $vehiculo): array
    {
        return array_values(Llanta::query()
            ->sinMontar()
            ->with(['detalleIngreso.ingreso', 'detalleSalida.salida'])
            ->orderBy('codigo')
            ->get()
            ->filter(fn (Llanta $llanta): bool => $llanta->detalleSalida === null || $llanta->detalleSalida->salida->vehiculo_id === $vehiculo->id)
            ->map(fn (Llanta $llanta): array => [
                'id' => $llanta->id,
                'codigo' => $llanta->codigo,
                'descripcion' => trim(implode(' ', array_filter([$llanta->marca, $llanta->medida]))),
                'lote' => $llanta->detalleIngreso?->ingreso->numero(),
                'vale' => $llanta->detalleSalida?->salida->numero(),
            ])
            ->all());
    }

    /**
     * La línea de vale con que la llanta sale del almacén hacia la unidad: la
     * que ya trae, una entrega a la unidad que aún tenga llantas de ese
     * artículo por montar o, si no hay ninguna, un vale que se emite ahora a
     * la orden de trabajo en ejecución que tiene pedida la llanta. Sin esa
     * orden no sale.
     *
     * @throws ValidationException si no hay stock o el mes está cerrado
     */
    public function valePara(Llanta $llanta, Vehiculo $vehiculo, string $fecha, ?string $entregadoA, ?User $usuario): DetalleSalida
    {
        if ($llanta->detalleSalida !== null) {
            return $llanta->detalleSalida;
        }

        $existente = DetalleSalida::query()
            ->whereIn('salida_almacen_id', SalidaAlmacen::query()->vigentes()->where('vehiculo_id', $vehiculo->id)->whereDate('fecha', '<=', $fecha)->select('id'))
            ->where('articulo_id', $llanta->articulo_id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->first(fn (DetalleSalida $detalle): bool => $this->disponibles($detalle) > 0);

        if ($existente !== null) {
            return $existente;
        }

        // Ninguna llanta sale del almacén sin una orden de trabajo que la
        // haya pedido y que ya esté en ejecución (con su foto del antes).
        $orden = $this->ordenQuePide($vehiculo, (int) $llanta->articulo_id);

        if ($orden === null) {
            throw ValidationException::withMessages(['llanta_id' => "Una llanta sale del almacén con una orden de trabajo: {$vehiculo->placa} no tiene una orden aprobada que tenga pedida esta llanta (o ya la recibió). Pídela como repuesto en la orden."]);
        }

        if (! $orden->estado->enEjecucion()) {
            throw ValidationException::withMessages(['llanta_id' => "La {$orden->numero()} todavía no se empezó: el mecánico tiene que iniciarla, con la foto de cómo llegó la unidad, antes de montar la llanta."]);
        }

        PeriodoAlmacen::exigirAbierto($fecha, 'fecha');

        $salida = SalidaAlmacen::create([
            'fecha' => $fecha,
            'destino' => DestinoSalida::OrdenTrabajo,
            'vehiculo_id' => $vehiculo->id,
            'orden_trabajo_id' => $orden->id,
            // Si no se dice otra cosa, se le entrega a quien la monta.
            'entregado_a' => filled($entregadoA) ? $entregadoA : (string) $usuario?->name,
            'observacion' => "Llanta {$llanta->codigo} para montar en {$vehiculo->placa}.",
            'user_id' => $usuario?->id,
        ]);
        $detalle = $salida->detalles()->create(['articulo_id' => $llanta->articulo_id, 'cantidad' => 1]);

        $this->kardex->registrarSalida($salida, $usuario?->id);

        return $detalle->refresh();
    }

    /**
     * La orden de trabajo abierta de la unidad, si tiene alguna.
     */
    public function ordenAbierta(Vehiculo $vehiculo): ?OrdenTrabajo
    {
        return OrdenTrabajo::query()->enTaller()->where('vehiculo_id', $vehiculo->id)->latest('id')->first();
    }

    /**
     * La orden abierta y aprobada de la unidad a la que todavía le falta
     * recibir ese artículo: el mecánico pidió la llanta en su requerimiento.
     */
    public function ordenQuePide(Vehiculo $vehiculo, int $articuloId): ?OrdenTrabajo
    {
        return OrdenTrabajo::query()
            ->enTaller()
            ->where('vehiculo_id', $vehiculo->id)
            ->orderBy('id')
            ->get()
            ->first(fn (OrdenTrabajo $orden): bool => ! $orden->estado->sinAprobar()
                && ($this->repuestos->pendientePorArticulo($orden)[$articuloId] ?? 0.0) >= 1);
    }

    /**
     * Lo que hay que hacer con las llantas, en números, para la portada.
     *
     * @return array{sin_codigo: int, en_almacen: int, entregadas: int, retiradas: int}
     */
    public function resumen(): array
    {
        $sinMontar = Llanta::query()->sinMontar()->get(['id', 'detalle_salida_id']);

        return [
            'sin_codigo' => $this->porCodificar(),
            'en_almacen' => $sinMontar->whereNull('detalle_salida_id')->count(),
            'entregadas' => $sinMontar->whereNotNull('detalle_salida_id')->count(),
            'retiradas' => Llanta::query()->whereNotNull('vehiculo_id')->whereNotNull('retirada_el')->where('motivo_retiro', '!=', MotivoRetiroLlanta::Dano)->count(),
        ];
    }

    /**
     * Las llantas sin montar de una entrega vuelven a figurar en el almacén:
     * la entrega se anuló o esas unidades se devolvieron.
     */
    public function soltar(DetalleSalida $detalle, ?int $cuantas = null): void
    {
        Llanta::query()
            ->sinMontar()
            ->where('detalle_salida_id', $detalle->id)
            ->orderByDesc('id')
            ->when($cuantas !== null, fn ($query) => $query->limit($cuantas))
            ->get()
            ->each(fn (Llanta $llanta) => $llanta->update(['detalle_salida_id' => null]));
    }

    /**
     * Dónde está la llanta, en palabras.
     *
     * @return array{situacion: string, donde: string}
     */
    public function ubicacion(Llanta $llanta): array
    {
        return match (true) {
            $llanta->estaMontada() => ['situacion' => 'montada', 'donde' => "{$llanta->vehiculo?->placa} · posición {$llanta->posicion}"],
            $llanta->retirada_el !== null => [
                'situacion' => $llanta->motivo_retiro === MotivoRetiroLlanta::Dano ? 'baja' : 'retirada',
                'donde' => "Retirada de {$llanta->vehiculo?->placa} ({$llanta->motivo_retiro?->label()})",
            ],
            $llanta->detalleSalida !== null => ['situacion' => 'entregada', 'donde' => "Entregada a {$llanta->detalleSalida->salida->vehiculo?->placa} con {$llanta->detalleSalida->salida->numero()}, sin montar"],
            default => ['situacion' => 'almacen', 'donde' => 'En el almacén'],
        };
    }

    /**
     * Las líneas de recepciones vigentes (compras, no devoluciones) con
     * artículos de la categoría de llantas.
     *
     * @return Builder<DetalleIngreso>
     */
    private function lineasDeIngreso(): Builder
    {
        $categoria = CategoriaArticulo::query()->where('clave', self::CLAVE_CATEGORIA)->value('id');

        return DetalleIngreso::query()
            ->whereIn('ingreso_almacen_id', IngresoAlmacen::query()->vigentes()->where('tipo', TipoIngreso::Compra)->select('id'))
            ->whereIn('articulo_id', Articulo::query()->where('categoria_articulo_id', $categoria ?? 0)->select('id'))
            ->orderBy('id');
    }
}
