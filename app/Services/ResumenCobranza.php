<?php

namespace App\Services;

use App\Enums\EstadoCobranza;
use App\Enums\Moneda;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Viaje;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La lectura de los viajes desde el dinero: qué cae bajo los filtros de la
 * cobranza, cuánto hay por cobrar y cuánto falta facturar.
 *
 * Vive fuera del controlador por lo mismo que `ResumenTablero`: traducir un
 * estado de cobranza a la presencia de facturas y de `fecha_pago`, o
 * decidir que los totales se suman por factura y no por fila, son reglas del
 * negocio y no de la petición.
 *
 * @phpstan-type FiltrosCobranza array{buscar: string|null, cliente: string|null, estado: string|null, mes: string|null, desde: string|null, hasta: string|null}
 */
class ResumenCobranza
{
    /**
     * Cuántos meses se desglosan en el resumen de «por facturar». Con más, la
     * fila de arriba compite con la tabla; el resto se alcanza por el filtro.
     */
    private const MESES_EN_RESUMEN = 4;

    /**
     * Filtro de trabajo, además de los estados de cobranza: los viajes con
     * una factura cobrada que no dice por dónde entró la plata. No es un
     * estado —la factura está cobrada—, es un dato que falta, y sin él ese
     * cobro no se puede cruzar con el estado de cuenta de ningún banco.
     */
    public const SIN_ENTIDAD = 'sin_entidad';

    /**
     * Los meses escritos acá y no sacados del locale de Carbon: el de la
     * aplicación está en inglés, y cambiarlo por una etiqueta de filtro
     * movería las fechas de todo lo demás.
     *
     * @var array<int, string>
     */
    public const MESES = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Los viajes que caen bajo los filtros, sin nada cargado encima: es la
     * base que comparten la tabla, los totales y el desglose por mes, y cada
     * una le agrega lo suyo.
     *
     * Los filtros de estado no son una columna: se traducen a la presencia de
     * facturas y de las fechas de cobro del neto y de la detracción. Con
     * varias facturas manda la más atrasada — la misma regla que
     * `Viaje::estadoCobranza()`.
     *
     * @param  FiltrosCobranza  $filtros
     * @return Builder<Viaje>
     */
    public function consulta(array $filtros): Builder
    {
        return Viaje::query()
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('numero_gr', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('cliente', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('destinatario', "%{$buscar}%", caseSensitive: false)
                        ->orWhereHas('facturas', fn ($query) => $query->whereLike('numero', "%{$buscar}%", caseSensitive: false));
                });
            })
            // El cliente de un viaje es a quién se le facturó. El de la GR
            // —quien despachó la carga— vale solo mientras el viaje no tenga
            // factura que diga otra cosa: Naturex despacha, pero paga la
            // tienda de Juliaca, y ese viaje es de la tienda.
            //
            // Llega el RUC, que es la identidad del cliente: el nombre cambia
            // según quién lo escribió («MINSUR S.A.», «MINSUR S. A.»). Un
            // nombre solo vale para lo que no trae RUC.
            ->when($filtros['cliente'], fn ($query, string $cliente) => $query->where(function ($query) use ($cliente): void {
                [$deLaGr, $deLaFactura] = self::esRuc($cliente)
                    ? ['cliente_ruc', 'cliente_ruc']
                    : ['cliente', 'cliente_razon_social'];

                $query->whereHas('facturas', fn ($query) => $query->where($deLaFactura, $cliente))
                    ->orWhere(fn ($query) => $query
                        ->where($deLaGr, $cliente)
                        ->whereDoesntHave('facturas', fn ($query) => $query->whereNotNull($deLaFactura)));
            }))
            ->when($filtros['mes'], function ($query, string $mes): void {
                // `Y-m` acotado a los días del mes en vez de `whereYear` +
                // `whereMonth`: así el índice de `fecha_traslado` sigue
                // sirviendo, que con 450 filas da igual pero no dentro de un año.
                $inicio = CarbonImmutable::parse("{$mes}-01")->startOfMonth();

                $query->whereBetween('fecha_traslado', [
                    $inicio->toDateString(),
                    $inicio->endOfMonth()->toDateString(),
                ]);
            })
            ->when($filtros['desde'], fn ($query, string $desde) => $query->where('fecha_traslado', '>=', $desde))
            ->when($filtros['hasta'], fn ($query, string $hasta) => $query->where('fecha_traslado', '<=', $hasta))
            ->when($filtros['estado'], function ($query, string $estado): void {
                match ($estado) {
                    EstadoCobranza::SinFacturar->value => $query->whereDoesntHave('facturas')->whereNull('no_facturable_at'),
                    EstadoCobranza::NoFacturable->value => $query->whereNotNull('no_facturable_at'),
                    EstadoCobranza::Facturado->value => $query->whereHas('facturas', fn ($query) => $query->whereNull('fecha_pago')),
                    EstadoCobranza::FaltaDetraccion->value => $query
                        ->whereDoesntHave('facturas', fn ($query) => $query->whereNull('fecha_pago'))
                        ->whereHas('facturas', fn ($query) => $this->conDetraccionPendiente($query)),
                    EstadoCobranza::Pagado->value => $query
                        ->whereHas('facturas')
                        ->whereDoesntHave('facturas', fn ($query) => $query
                            ->whereNull('fecha_pago')
                            ->orWhere(function ($query): void {
                                $this->conDetraccionPendiente($query);
                            })),
                    self::SIN_ENTIDAD => $query->whereHas('facturas', fn ($query) => $this->cobradasSinEntidad($query)),
                    default => null,
                };
            });
    }

    /**
     * Facturas que llevan detracción y todavía no se depositó: la misma
     * condición que `Factura::faltaDetraccion()`.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function conDetraccionPendiente(Builder $query): Builder
    {
        return $query->where('detraccion', '>', 0)
            ->whereNull('fecha_detraccion')
            ->where('detraccion_por_empresa', false);
    }

    /**
     * Facturas con el neto cobrado y sin entidad: ni banco ni caja.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function cobradasSinEntidad(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_pago')->whereNull('cuenta_bancaria_id');
    }

    /**
     * Los estados del filtro: los de cobranza más el de trabajo.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opcionesDeEstado(): array
    {
        return [
            ...EstadoCobranza::options(),
            ['value' => self::SIN_ENTIDAD, 'label' => 'Cobrada sin entidad'],
        ];
    }

    /**
     * Lo que el contador viene a ver: cuánto hay por cobrar y cuánto ya entró,
     * bajo los mismos filtros de la tabla. Separado por moneda porque sumar
     * soles con dólares da un número que no significa nada.
     *
     * Por cobrar es lo que falta que deposite el cliente, en sus dos partes:
     * el neto a la cuenta de la empresa y la detracción al Banco de la
     * Nación. Cobrado es la suma de lo que ya entró por cualquiera de los dos.
     * Cuando la detracción la deposita la empresa no es por cobrar ni cobrado:
     * va aparte, como lo que la empresa tiene pendiente de depositar.
     *
     * La detracción se deposita siempre en soles, así que va siempre en el
     * bloque de soles, aunque la factura sea en dólares.
     *
     * Los totales se calculan sobre facturas distintas, no sobre filas: una
     * factura que cubre tres viajes aparece en tres filas y sumarla tres veces
     * inflaría el saldo. Las varias facturas de un mismo viaje, en cambio, sí
     * suman cada una: son cobros distintos.
     *
     * Una factura con GR dentro y fuera del recorte aporta solo la parte de
     * las que están dentro (ver `partes()`): así los meses suman el total.
     *
     * @param  FiltrosCobranza  $filtros
     * @return array<string, mixed>
     */
    public function totales(array $filtros): array
    {
        $partes = $this->partes($filtros);

        $monedas = $partes->map(fn (ParteDeFactura $parte): Moneda => $parte->factura->moneda)
            ->when($partes->contains(fn (ParteDeFactura $parte): bool => (float) $parte->factura->detraccion > 0), fn ($monedas) => $monedas->push(Moneda::Soles))
            ->unique(fn (Moneda $moneda): string => $moneda->value)
            ->sortBy(fn (Moneda $moneda): int => $moneda === Moneda::Soles ? 0 : 1);

        $porMoneda = $monedas
            ->map(function (Moneda $moneda) use ($partes): array {
                $propias = $partes->filter(fn (ParteDeFactura $parte): bool => $parte->factura->moneda === $moneda);
                $detracciones = $moneda === Moneda::Soles ? $partes : $partes->take(0);

                return [
                    'moneda' => $moneda->value,
                    'simbolo' => $moneda->simbolo(),
                    'por_cobrar' => round($propias
                        ->filter(fn (ParteDeFactura $parte): bool => $parte->factura->fecha_pago === null)
                        ->sum(fn (ParteDeFactura $parte): float => $parte->de($parte->factura->neto)), 2),
                    'detraccion_por_cobrar' => round($detracciones
                        ->filter(fn (ParteDeFactura $parte): bool => $parte->factura->faltaDetraccion())
                        ->sum(fn (ParteDeFactura $parte): float => $parte->de($parte->factura->detraccion)), 2),
                    'detraccion_por_depositar' => round($detracciones
                        ->filter(fn (ParteDeFactura $parte): bool => $parte->factura->detraccionPorDepositar())
                        ->sum(fn (ParteDeFactura $parte): float => $parte->de($parte->factura->detraccion)), 2),
                    'cobrado' => round(
                        $propias
                            ->filter(fn (ParteDeFactura $parte): bool => $parte->factura->fecha_pago !== null)
                            ->sum(fn (ParteDeFactura $parte): float => $parte->de($parte->factura->neto))
                        + $detracciones->sum(fn (ParteDeFactura $parte): float => $parte->de($parte->factura->detraccionCobrada())),
                        2,
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'montos' => $porMoneda,
            'por_facturar' => $this->porFacturarPorMes($filtros),
            'facturas' => $partes->count(),
            // Las que también cubren GR fuera del recorte: de esas solo entró
            // una parte, y la pantalla lo dice para que no se lea como exacto.
            'repartidas' => $partes->reject(fn (ParteDeFactura $parte): bool => $parte->completa())->count(),
            // Cobradas sin decir por dónde entró la plata: no se pueden
            // cruzar con el banco, así que se avisan hasta que se completen.
            'sin_entidad' => $partes
                ->filter(fn (ParteDeFactura $parte): bool => $parte->factura->fecha_pago !== null && $parte->factura->cuenta_bancaria_id === null)
                ->count(),
            // Una factura registrada sin monto suma cero y desaparecería del
            // total sin que nadie lo note; se cuenta aparte para que se vea.
            'sin_monto' => $partes->filter(fn (ParteDeFactura $parte): bool => $parte->factura->monto === null)->count(),
        ];
    }

    /**
     * Las facturas que caen bajo los filtros, cada una con las GR suyas que
     * quedaron dentro. Es el recorte del dinero, que comparten los totales de
     * la pantalla y el resumen del Excel.
     *
     * Dos reglas evitan contar una factura dos veces:
     *
     * - Por fecha, estado o búsqueda, la factura aporta la parte de sus GR
     *   que caen dentro (`ParteDeFactura`).
     * - Por cliente no se reparte: la factura es entera de a quien se le
     *   facturó, aunque alguna de sus GR nombre a otro cliente. La deuda es
     *   de uno solo.
     *
     * @param  FiltrosCobranza  $filtros
     * @param  array<int|string, mixed>  $relaciones  Lo que haya que cargar en cada factura.
     * @return Collection<int, ParteDeFactura>
     */
    public function partes(array $filtros, array $relaciones = []): Collection
    {
        $sinCliente = [...$filtros, 'cliente' => null];
        $delRecorte = fn (): Builder => $this->consulta($sinCliente)->reorder()->select('viajes.id');

        $facturas = Factura::query()
            ->whereHas('viajes', fn ($query) => $query->whereIn('viajes.id', $delRecorte()))
            ->when($filtros['cliente'], fn ($query, string $cliente) => $this->facturadasA($query, $cliente))
            ->with($relaciones)
            ->orderBy('fecha_emision')
            ->orderBy('numero')
            ->get();

        // Del pivote y no de la relación: se necesitan solo los ids, y la
        // relación puede venir cargada con otro orden o con otras columnas.
        $viajesPorFactura = Viaje::query()
            ->join('factura_viaje', 'factura_viaje.viaje_id', '=', 'viajes.id')
            ->whereIn('factura_viaje.factura_id', $facturas->modelKeys())
            ->toBase()
            ->get(['factura_viaje.factura_id', 'viajes.id'])
            ->groupBy('factura_id')
            ->map(fn (Collection $filas): array => $filas->map(fn (object $fila): int => (int) $fila->id)->values()->all());

        $enRecorte = Viaje::query()
            ->whereIn('viajes.id', $viajesPorFactura->flatten()->unique()->values()->all())
            ->whereIn('viajes.id', $delRecorte())
            ->pluck('viajes.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->flip();

        return $facturas
            ->map(function (Factura $factura) use ($viajesPorFactura, $enRecorte): ParteDeFactura {
                /** @var list<int> $ids */
                $ids = $viajesPorFactura->get($factura->id, []);

                return new ParteDeFactura(
                    $factura,
                    $ids,
                    array_values(array_filter($ids, fn (int $id): bool => $enRecorte->has($id))),
                );
            })
            ->toBase();
    }

    /**
     * Las facturas emitidas a un cliente. El filtro trae su RUC; si trae un
     * nombre (lo que no tiene RUC, o un enlace viejo) también se resuelve por
     * RUC: la misma empresa sale como «MINSUR S.A.» en la GR y «MINSUR S. A.»
     * en el PDF de la factura. Sin RUC en la factura vale el nombre impreso,
     * y sin eso el cliente de sus GR.
     *
     * @param  Builder<Factura>  $query
     * @return Builder<Factura>
     */
    private function facturadasA(Builder $query, string $cliente): Builder
    {
        if (self::esRuc($cliente)) {
            return $query->where(function (Builder $query) use ($cliente): void {
                $query->where('cliente_ruc', $cliente)
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNull('cliente_ruc')
                        ->whereHas('viajes', fn ($query) => $query->where('cliente_ruc', $cliente)));
            });
        }

        $rucs = Viaje::query()->where('cliente', $cliente)->whereNotNull('cliente_ruc')->distinct()->pluck('cliente_ruc')
            ->merge(Factura::query()->where('cliente_razon_social', $cliente)->whereNotNull('cliente_ruc')->distinct()->pluck('cliente_ruc'))
            ->unique()
            ->values()
            ->all();

        return $query->where(function (Builder $query) use ($cliente, $rucs): void {
            $query->whereIn('cliente_ruc', $rucs)
                ->orWhere(fn (Builder $query) => $query->whereNull('cliente_ruc')->where('cliente_razon_social', $cliente))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('cliente_ruc')
                    ->whereNull('cliente_razon_social')
                    ->whereHas('viajes', fn ($query) => $query->where('cliente', $cliente)));
        });
    }

    private static function esRuc(string $cliente): bool
    {
        return preg_match('/^\d{11}$/', $cliente) === 1;
    }

    /**
     * Los clientes del filtro: una opción por RUC, no por nombre. Son a
     * quienes se les facturó, más el cliente de las GR que todavía no tienen
     * factura —provisional, hasta que la factura diga quién paga—. Quien solo
     * despachó carga que se le facturó a otro no es cliente y no aparece.
     *
     * Se muestra el alias del padrón, y si el RUC no está en él, el nombre
     * de la GR. Lo que no trae RUC queda por nombre.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function opcionesDeCliente(): array
    {
        $alias = Cliente::query()->pluck('alias', 'ruc');

        $facturados = Factura::query()
            ->whereHas('viajes')
            ->whereNotNull('cliente_ruc')
            ->whereNotNull('cliente_razon_social')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get(['cliente_ruc', 'cliente_razon_social'])
            ->unique('cliente_ruc')
            ->map(fn (Factura $factura): array => ['ruc' => (string) $factura->cliente_ruc, 'nombre' => (string) $factura->cliente_razon_social]);

        $deLasGr = Viaje::query()
            ->whereDoesntHave('facturas')
            ->whereNotNull('cliente_ruc')
            ->orderByDesc('fecha_traslado')
            ->toBase()
            ->get(['cliente_ruc', 'cliente'])
            ->unique('cliente_ruc')
            ->map(fn (object $viaje): array => ['ruc' => (string) $viaje->cliente_ruc, 'nombre' => (string) $viaje->cliente]);

        $conRuc = $facturados->toBase()->concat($deLasGr)
            ->unique('ruc')
            ->map(fn (array $cliente): array => [
                'value' => $cliente['ruc'],
                'label' => $alias->get($cliente['ruc']) ?? ImportadorViaje::normalizarRazonSocial($cliente['nombre']),
            ]);

        $sinRuc = Viaje::query()->whereDoesntHave('facturas')->whereNull('cliente_ruc')->distinct()->pluck('cliente')
            ->concat(Factura::query()->whereHas('viajes')->whereNull('cliente_ruc')->whereNotNull('cliente_razon_social')->distinct()->pluck('cliente_razon_social'))
            ->filter()
            ->unique()
            ->map(fn (string $nombre): array => ['value' => $nombre, 'label' => $nombre]);

        return $conRuc->concat($sinRuc)
            ->sortBy(fn (array $opcion): string => mb_strtolower($opcion['label']))
            ->values()
            ->all();
    }

    /**
     * Cómo se nombra el cliente del filtro, para decirlo en un reporte.
     */
    public function nombreDeCliente(string $cliente): string
    {
        return collect($this->opcionesDeCliente())->firstWhere('value', $cliente)['label'] ?? $cliente;
    }

    /**
     * Los meses que tienen viajes, del más reciente al más antiguo. Se derivan
     * de las fechas —no hay tabla de periodos— igual que las ciudades salen de
     * las direcciones; se piden las fechas distintas y no las filas para que
     * el mapeo en PHP no crezca con el historial.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function opcionesDeMes(): array
    {
        return Viaje::query()
            ->select('fecha_traslado')
            ->distinct()
            ->pluck('fecha_traslado')
            ->map(fn (CarbonImmutable|Carbon $fecha): string => $fecha->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (string $mes): array => [
                'value' => $mes,
                'label' => self::etiquetaDeMes($mes),
            ])
            ->all();
    }

    /**
     * Lo que falta facturar, desglosado por mes. Un solo número global no dice
     * nada útil cuando arrastra medio año: la pregunta real es cuánto de
     * setiembre queda por facturar, porque ese es el trabajo de la semana.
     *
     * Se traen solo las fechas —no las filas— y se agrupan en PHP, igual que
     * las ciudades y los meses del filtro: no hay función de mes portable
     * entre el Postgres de producción y el SQLite de los tests.
     *
     * @param  FiltrosCobranza  $filtros
     * @return array<int, array{mes: string, label: string, viajes: int}>
     */
    private function porFacturarPorMes(array $filtros): array
    {
        return $this->consulta($filtros)
            ->whereDoesntHave('facturas')
            // Lo que se decidió no cobrar no está pendiente de nada.
            ->whereNull('no_facturable_at')
            ->reorder()
            ->pluck('fecha_traslado')
            ->groupBy(fn (CarbonImmutable|Carbon $fecha): string => $fecha->format('Y-m'))
            ->map(fn (Collection $delMes, string $mes): array => [
                'mes' => $mes,
                'label' => self::etiquetaDeMes($mes),
                'viajes' => $delMes->count(),
            ])
            ->sortKeysDesc()
            // Los meses viejos sin facturar siguen contando en su propio
            // filtro; acá sobrarían: el ancho es para lo que está en curso.
            ->take(self::MESES_EN_RESUMEN)
            ->values()
            ->all();
    }

    /**
     * «2026-09» leído como «Septiembre 2026». El idioma se fija acá y no se
     * hereda del locale de la app, que está en inglés.
     */
    private static function etiquetaDeMes(string $mes): string
    {
        return self::MESES[(int) substr($mes, 5, 2)].' '.substr($mes, 0, 4);
    }
}
