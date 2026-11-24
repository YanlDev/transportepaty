<?php

namespace App\Services;

use App\Enums\DestinoSalida;
use App\Enums\Moneda;
use App\Enums\TipoComprobante;
use App\Enums\TipoIngreso;
use App\Enums\TipoValeCompra;
use App\Models\DetalleIngreso;
use App\Models\DetalleValeCompra;
use App\Models\IngresoAlmacen;
use App\Models\MovimientoAlmacen;
use App\Models\SalidaAlmacen;
use App\Models\User;
use App\Models\ValeCompra;
use Illuminate\Validation\ValidationException;

/**
 * Recibir una compra en dos pasos: primero la mercadería, apenas llega, y
 * después el comprobante.
 *
 * En el primer paso el stock entra con los precios del pedido aprobado, como
 * si fuera una compra sin crédito fiscal (lo que se paga es el costo). Al
 * completar el comprobante se pone lo que dice el papel y el costo se
 * corrige: con factura baja al valor sin IGV, y el kardex se recalcula.
 *
 * Una emergencia en ruta marcada «directo a la unidad» hace las dos cosas
 * sola al aprobarse: entra y sale a la unidad, porque el repuesto nunca pasó
 * por el almacén.
 */
class RecepcionDeCompra
{
    public function __construct(
        private readonly Kardex $kardex,
        private readonly CostoDeCompra $costo,
        private readonly UsoDeVale $usoDeVale,
    ) {}

    /**
     * Lo que al pedido le falta recibir, por artículo.
     *
     * @return list<array{articulo_id: int, descripcion: string, unidad: string, autorizado: float, recibido: float, pendiente: float}>
     */
    public function pendientes(ValeCompra $vale): array
    {
        $yaEntro = DetalleIngreso::query()
            ->where('bonificacion', false)
            ->whereIn('ingreso_almacen_id', $vale->ingresos()->vigentes()->select('id'))
            ->groupBy('articulo_id')
            ->selectRaw('articulo_id, sum(cantidad) as cantidad')
            ->pluck('cantidad', 'articulo_id');

        return array_values($vale->detalles()->with('articulo')->where('es_servicio', false)->get()
            ->map(fn (DetalleValeCompra $detalle): array => [
                'articulo_id' => (int) $detalle->articulo_id,
                'descripcion' => $detalle->descripcion,
                'unidad' => ($detalle->articulo?->unidad_compra ?? $detalle->articulo?->unidad)?->abreviatura() ?? '',
                'autorizado' => (float) $detalle->cantidad,
                'recibido' => (float) ($yaEntro[$detalle->articulo_id] ?? 0),
                'pendiente' => round(max((float) $detalle->cantidad - (float) ($yaEntro[$detalle->articulo_id] ?? 0), 0), 3),
            ])
            ->filter(fn (array $linea): bool => $linea['pendiente'] > 0.0005)
            ->all());
    }

    /**
     * Si el pedido se puede recibir sin comprobante: aprobado, vigente y con
     * algo por recibir. En dólares se pide el tipo de cambio del día.
     */
    public function sePuedeRecibir(ValeCompra $vale): bool
    {
        return $vale->situacion()->sirveParaComprar()
            && $this->pendientes($vale) !== [];
    }

    /**
     * Paso 1: llega la mercadería. Entra al stock con los precios del pedido
     * y queda marcada «falta el comprobante».
     *
     * En dólares hace falta el tipo de cambio: el kardex va en soles. Se
     * puede corregir al completar el comprobante.
     *
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     */
    public function recibir(ValeCompra $vale, array $lineas, string $fecha, ?User $usuario, ?string $observacion = null, ?float $tipoCambio = null): IngresoAlmacen
    {
        $vale = ValeCompra::query()->lockForUpdate()->with(['detalles', 'proveedor'])->findOrFail($vale->id);

        $enDolares = $vale->moneda === Moneda::Dolares;

        if ($enDolares && ($tipoCambio === null || $tipoCambio <= 0)) {
            $this->rechazar("El {$vale->numero()} es en dólares: pon el tipo de cambio del día.");
        }

        $precios = $vale->detalles->where('es_servicio', false)->keyBy('articulo_id');
        $pendientes = collect($this->pendientes($vale))->keyBy('articulo_id');
        $completo = true;

        foreach ($lineas as $linea) {
            $pendiente = $pendientes->get($linea['articulo_id']);

            if ($pendiente === null || $linea['cantidad'] - $pendiente['pendiente'] > 0.0005) {
                $this->rechazar("El {$vale->numero()} no tiene eso por recibir: revisa los artículos y las cantidades.");
            }

            $completo = $completo && abs($linea['cantidad'] - $pendiente['pendiente']) <= 0.0005;
        }

        if (! $vale->permite_parciales && (! $completo || count($lineas) !== $pendientes->count())) {
            $this->rechazar("El {$vale->numero()} es de una sola compra: recibe todo lo pedido, o pide que le activen las compras en partes.");
        }

        $calculables = array_map(fn (array $linea): array => [
            'cantidad' => $linea['cantidad'],
            'precio_unitario' => (float) $precios[$linea['articulo_id']]->precio_unitario,
            'factor' => $precios[$linea['articulo_id']]->articulo?->factor() ?? 1.0,
            'bonificacion' => false,
        ], $lineas);

        // Sin comprobante todavía: lo pagado es el costo, como en una nota
        // de venta. Si después llega una factura, el costo baja.
        $calculo = $this->costo->calcular(TipoComprobante::NotaVenta, $vale->moneda, $enDolares ? $tipoCambio : null, true, $calculables);

        $error = $this->usoDeVale->error($vale, $vale->proveedor_id, $vale->moneda, $fecha, $calculo['total'], array_map(
            fn (array $linea): array => ['articulo_id' => $linea['articulo_id'], 'cantidad' => $linea['cantidad'], 'bonificacion' => false],
            $lineas,
        ));

        if ($error !== null) {
            $this->rechazar($error);
        }

        $ingreso = IngresoAlmacen::create([
            'tipo' => TipoIngreso::Compra,
            'fecha' => $fecha,
            'fecha_emision' => $fecha,
            'proveedor_id' => $vale->proveedor_id,
            'vale_compra_id' => $vale->id,
            'tipo_comprobante' => null,
            'comprobante_pendiente' => true,
            'moneda' => $vale->moneda,
            'tipo_cambio' => $enDolares ? $tipoCambio : null,
            'precios_con_igv' => true,
            'descuento' => 0,
            'flete' => 0,
            'subtotal' => $calculo['subtotal'],
            'igv' => 0,
            'total' => $calculo['total'],
            'costo_total' => $calculo['costo_total'],
            'observacion' => $observacion,
            'user_id' => $usuario?->id,
        ]);

        foreach ($lineas as $i => $linea) {
            $ingreso->detalles()->create([
                'articulo_id' => $linea['articulo_id'],
                'bonificacion' => false,
                'cantidad' => $calculables[$i]['cantidad'],
                'precio_unitario' => $calculables[$i]['precio_unitario'],
                'factor' => $calculables[$i]['factor'],
                ...$calculo['lineas'][$i],
            ]);
        }

        $this->kardex->registrarIngreso($ingreso, $usuario?->id);

        return $ingreso;
    }

    /**
     * Paso 2: llega el comprobante. Se pone lo que dice el papel y el costo
     * de lo que ya entró se corrige; el kardex se rehace desde ahí, así que
     * lo que ya se entregó a una orden también queda a su costo real.
     *
     * @param  array<string, mixed>  $datos  lo validado por `CompletarIngresoRequest`
     * @param  array{subtotal: float, total: float, costo_total: float, lineas: list<array{cantidad_base: float, costo_unitario: float, costo_total: float}>}  $calculo
     * @param  list<float>  $precios  el precio de cada línea, en su orden
     */
    public function completar(IngresoAlmacen $ingreso, array $datos, array $calculo, array $precios): void
    {
        $comprobante = TipoComprobante::from($datos['tipo_comprobante']);
        $total = round((float) $datos['total_comprobante'], 2);

        $ingreso->update([
            'fecha_emision' => $datos['fecha_emision'],
            'tipo_comprobante' => $comprobante,
            'comprobante_pendiente' => false,
            'serie_numero' => $datos['serie_numero'] ?? null,
            'comprobante_clave' => IngresoAlmacen::claveComprobante($datos['serie_numero'] ?? null),
            'tipo_cambio' => $ingreso->moneda === Moneda::Dolares ? ($datos['tipo_cambio'] ?? $ingreso->tipo_cambio) : null,
            'precios_con_igv' => (bool) ($datos['precios_con_igv'] ?? false),
            'descuento' => (float) ($datos['descuento'] ?? 0),
            'flete' => (float) ($datos['flete'] ?? 0),
            'subtotal' => $calculo['subtotal'],
            'igv' => $comprobante->llevaIgv() ? round($total - $calculo['subtotal'], 2) : 0,
            'total' => $total,
            'costo_total' => $calculo['costo_total'],
        ]);

        $detalles = $ingreso->detalles()->orderBy('id')->get();

        foreach ($detalles as $i => $detalle) {
            $detalle->update(['precio_unitario' => $precios[$i], ...$calculo['lineas'][$i]]);

            // El movimiento no se borra (puede haber salidas después): se
            // le corrige el costo y el nombre del documento.
            MovimientoAlmacen::query()
                ->where('origen_type', $detalle->getMorphClass())
                ->where('origen_id', $detalle->id)
                ->update([
                    'documento' => $ingreso->documento(),
                    'costo_unitario' => $detalle->costo_unitario,
                    'costo_total' => $detalle->costo_total,
                ]);
        }

        $this->kardex->recalcular(array_values($detalles->pluck('articulo_id')->unique()->all()));
    }

    /**
     * Una emergencia que ya se instaló en la unidad: al aprobarse, lo
     * comprado entra y sale a la unidad en el mismo acto. Queda pendiente
     * solo el comprobante.
     */
    public function directoALaUnidad(ValeCompra $vale, ?User $usuario): ?SalidaAlmacen
    {
        if ($vale->tipo !== TipoValeCompra::Emergencia || ! $vale->directo_a_unidad || $vale->vehiculo_id === null) {
            return null;
        }

        $lineas = array_map(
            fn (array $linea): array => ['articulo_id' => $linea['articulo_id'], 'cantidad' => $linea['pendiente']],
            $this->pendientes($vale),
        );

        if ($lineas === [] || $vale->moneda !== Moneda::Soles) {
            return null;
        }

        $hoy = RelojOperativo::hoy();
        $ingreso = $this->recibir($vale, $lineas, $hoy, $usuario, 'Emergencia en ruta: directo a la unidad.');

        $salida = SalidaAlmacen::create([
            'fecha' => $hoy,
            'destino' => $vale->orden_trabajo_id !== null ? DestinoSalida::OrdenTrabajo : DestinoSalida::Unidad,
            'vehiculo_id' => $vale->vehiculo_id,
            'orden_trabajo_id' => $vale->orden_trabajo_id,
            'entregado_a' => $vale->solicitante?->name ?? 'En ruta',
            'observacion' => "Emergencia en ruta ({$vale->numero()}): se instaló sin pasar por el almacén.",
            'user_id' => $usuario?->id,
        ]);

        foreach ($ingreso->detalles as $detalle) {
            $salida->detalles()->create(['articulo_id' => $detalle->articulo_id, 'cantidad' => $detalle->cantidad_base]);
        }

        $this->kardex->registrarSalida($salida, $usuario?->id);

        return $salida;
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['recepcion' => $mensaje]);
    }
}
