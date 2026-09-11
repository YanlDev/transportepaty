<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCobranza;
use App\Enums\Moneda;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\ResumenCobranza;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La misma tabla de viajes, leída desde la cobranza: qué se facturó, cuánto,
 * cuándo entró la plata y por qué cuenta. Vive aparte de `/viajes` porque son
 * dos lecturas distintas del mismo dato —la operación no necesita ver montos,
 * y la contabilidad no necesita ver placas ni pesos— y meter las dos en una
 * sola tabla la volvía ilegible.
 *
 * Los recuentos viven en `ResumenCobranza`; acá queda leer los filtros de la
 * petición y armar la fila tal como la dibuja la tabla.
 */
class ContabilidadController extends Controller
{
    public function __construct(private readonly ResumenCobranza $cobranza) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Factura::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'cliente' => $request->string('cliente')->trim()->value(),
            'estado' => $request->string('estado')->trim()->value(),
            'mes' => $this->mesValido($request->string('mes')->trim()->value()),
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];

        $viajes = $this->cobranza->consulta($filtros)
            // Las relaciones se cargan solo acá: las otras dos ramas que usan
            // la misma consulta terminan en un subselect y en un `pluck()`,
            // donde un `with()` no aporta nada.
            //
            // Son las mismas de `/viajes` —la tabla muestra las mismas
            // columnas de operación— más la factura. `media` evita el N+1 de
            // `getFirstMediaUrl()`, y `factura.viajes` se trae solo con la
            // llave para saber cuántos viajes cubre cada factura sin una
            // consulta por fila.
            ->with([
                'tracto:id,placa',
                'carreta:id,placa',
                'conductor:id,nombres,apellidos',
                'clienteDelPadron:id,alias',
                'media',
                'factura.cuentaBancaria:id,alias,banco',
                'factura.viajes:id,factura_id',
            ])
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Viaje $viaje): array => $this->filaContable($viaje));

        return Inertia::render('contabilidad/index', [
            'viajes' => $viajes,
            'filtros' => $filtros,
            'resumen' => $this->cobranza->totales($filtros),
            'estados' => EstadoCobranza::options(),
            'monedas' => Moneda::options(),
            'clientes' => Viaje::opcionesDeCliente(),
            'meses' => $this->cobranza->opcionesDeMes(),
            'cuentas' => $this->opcionesCuentas(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaContable(Viaje $viaje): array
    {
        $factura = $viaje->factura;

        return [
            // Las columnas de operación son las mismas de `/viajes`: acá no se
            // factura contra un resumen, se factura contra el viaje entero.
            ...$viaje->datosDeListado(),
            'estado' => $viaje->estadoCobranza()->value,
            'estado_label' => $viaje->estadoCobranza()->label(),
            'factura' => $factura === null ? null : [
                'id' => $factura->id,
                'numero' => $factura->numero,
                'fecha_emision' => $factura->fecha_emision->toDateString(),
                'monto' => $factura->monto === null ? null : (float) $factura->monto,
                'moneda' => $factura->moneda->value,
                'simbolo' => $factura->moneda->simbolo(),
                'fecha_pago' => $factura->fecha_pago?->toDateString(),
                'dias_vencida' => $factura->diasVencida(),
                'cuenta_bancaria_id' => $factura->cuenta_bancaria_id,
                'cuenta' => $factura->cuentaBancaria?->alias,
                'observacion' => $factura->observacion,
                // Cuántos viajes cubre: la fila muestra el monto completo de
                // la factura, y sin esto parecería que cada una de las tres
                // filas de una factura agrupada cobró ese monto por separado.
                'viajes_count' => $factura->viajes->count(),
                // Los ids van con la fila para poder editar la factura sin
                // otra ida al servidor, y sobre todo sin perder los viajes que
                // cayeron en otra página del listado.
                'viaje_ids' => $factura->viajes->pluck('id')->all(),
            ],
        ];
    }

    /**
     * El mes del filtro solo si viene con la forma `YYYY-MM`. Llega por query
     * string, así que cualquier otra cosa se descarta en vez de dejar que
     * `parse()` reviente con un 500 sobre un texto arbitrario.
     */
    private function mesValido(?string $mes): ?string
    {
        if ($mes === null || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) !== 1) {
            return null;
        }

        return $mes;
    }

    /**
     * Solo las cuentas activas: las cerradas siguen apareciendo en las
     * facturas viejas, pero no deben ofrecerse para un cobro nuevo.
     *
     * @return array<int, array{id: int, alias: string, banco: string, numero_cuenta: string, moneda: string}>
     */
    private function opcionesCuentas(): array
    {
        return CuentaBancaria::query()
            ->where('activa', true)
            ->orderBy('alias')
            ->get(['id', 'alias', 'banco', 'numero_cuenta', 'moneda'])
            ->map(fn (CuentaBancaria $cuenta): array => [
                'id' => $cuenta->id,
                'alias' => $cuenta->alias,
                'banco' => $cuenta->banco,
                'numero_cuenta' => $cuenta->numero_cuenta,
                'moneda' => $cuenta->moneda->value,
            ])
            ->all();
    }
}
