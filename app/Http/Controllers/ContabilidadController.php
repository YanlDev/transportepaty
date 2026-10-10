<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCobranza;
use App\Enums\Moneda;
use App\Http\Requests\MarcarGrFisicaRequest;
use App\Http\Requests\MarcarNoFacturableRequest;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\AsociadorFacturas;
use App\Services\ExportadorCobranza;
use App\Services\ResumenCobranza;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La misma tabla de viajes, leída desde la cobranza: qué se facturó, cuánto,
 * cuándo entró la plata y por qué cuenta. Vive aparte de `/viajes` porque son
 * dos lecturas distintas del mismo dato —la operación no necesita ver montos,
 * y la contabilidad no necesita ver placas ni pesos— y meter las dos en una
 * sola tabla la volvía ilegible.
 *
 * Los recuentos viven en `ResumenCobranza`; acá queda leer los filtros de la
 * petición y armar la fila tal como la dibuja la tabla.
 *
 * @phpstan-import-type FiltrosCobranza from ResumenCobranza
 */
class ContabilidadController extends Controller
{
    public function __construct(
        private readonly ResumenCobranza $cobranza,
        private readonly ExportadorCobranza $exportador,
        private readonly AsociadorFacturas $asociador,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Factura::class);

        $filtros = $this->filtros($request);

        $viajes = $this->cobranza->consulta($filtros)
            // Las relaciones se cargan solo acá: las otras dos ramas que usan
            // la misma consulta terminan en un subselect y en un `pluck()`,
            // donde un `with()` no aporta nada.
            //
            // Son las mismas de `/viajes` —la tabla muestra las mismas
            // columnas de operación— más sus facturas. `media` evita el N+1 de
            // `getFirstMediaUrl()`, y `facturas.viajes` se trae solo con la
            // llave para saber cuántos viajes cubre cada factura sin una
            // consulta por fila.
            ->with([
                'tracto:id,placa',
                'carreta:id,placa',
                'conductor:id,nombres,apellidos',
                'clienteDelPadron:id,alias',
                'media',
                'facturas' => fn ($query) => $query->orderBy('fecha_emision')->orderBy('facturas.id'),
                'facturas.cuentaBancaria:id,alias,banco',
                'facturas.viajes:viajes.id',
                'facturas.media',
            ])
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('numero_gr')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Viaje $viaje): array => $this->filaContable($viaje));

        return Inertia::render('contabilidad/index', [
            'viajes' => $viajes,
            'filtros' => $filtros,
            'resumen' => $this->cobranza->totales($filtros),
            // Los catálogos de los selectores no dependen de los filtros: van
            // en closures para que una búsqueda que no los pide no los arme.
            'estados' => fn (): array => EstadoCobranza::options(),
            'monedas' => fn (): array => Moneda::options(),
            'clientes' => fn (): array => Viaje::opcionesDeCliente(),
            'meses' => fn (): array => $this->cobranza->opcionesDeMes(),
            'cuentas' => fn (): array => $this->opcionesCuentas(),
            // No dependen de los filtros: una factura sin GR no cae en ningún
            // mes ni cliente de la tabla, y por eso va aparte.
            'facturasPorAsociar' => fn (): array => $this->facturasPorAsociar(),
        ]);
    }

    /**
     * La misma tabla que `index()`, en un .xlsx, con los filtros que venían en
     * la URL. Sin paginar a propósito: lo que se descarga es todo lo que cae
     * bajo el filtro, no la página que quedó abierta.
     */
    public function exportar(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Factura::class);

        // El archivo se arma en disco y no en memoria porque el escritor de
        // PhpSpreadsheet necesita un archivo real para el .zip del .xlsx.
        // `deleteFileAfterSend` lo borra apenas termina la descarga.
        $ruta = tempnam(sys_get_temp_dir(), 'cobranza');

        $this->exportador->escribir($this->filtros($request), $ruta);

        return response()
            ->download($ruta, $this->exportador->nombreArchivo())
            ->deleteFileAfterSend();
    }

    /**
     * Marca o desmarca que el papel de la GR ya está en la oficina. Lo hace
     * quien puede facturar, igual que el resto de la cobranza.
     *
     * Volver a marcar una GR que ya estaba marcada no le cambia la fecha: la
     * que vale es la primera vez que se registró que llegó.
     */
    public function marcarGrFisica(MarcarGrFisicaRequest $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('create', Factura::class);

        $recibida = $request->boolean('recibida');

        $viaje->update([
            'gr_fisica_recibida_at' => $recibida ? ($viaje->gr_fisica_recibida_at ?? now()) : null,
        ]);

        return back();
    }

    /**
     * Marca o desmarca una GR como «no se factura»: la cajita que viajó con la
     * carga grande, una cortesía. Sale de lo pendiente sin anularla, porque
     * ante SUNAT sigue valiendo y el viaje cuenta en la operación.
     */
    public function marcarNoFacturable(MarcarNoFacturableRequest $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('create', Factura::class);

        $noFacturable = $request->boolean('no_facturable');

        $viaje->update([
            'no_facturable_at' => $noFacturable ? ($viaje->no_facturable_at ?? now()) : null,
            'motivo_no_facturable' => $noFacturable ? $request->validated('motivo') : null,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $noFacturable
                ? "La GR {$viaje->numero_gr} quedó como «no se factura»."
                : "La GR {$viaje->numero_gr} vuelve a estar por facturar.",
        ]);
    }

    /**
     * Los filtros de la cobranza tal como llegan en la URL. Los comparten la
     * tabla y la exportación, que tienen que mirar exactamente el mismo
     * recorte: si divergen, el archivo deja de ser lo que se ve en pantalla.
     *
     * @return FiltrosCobranza
     */
    private function filtros(Request $request): array
    {
        return [
            'buscar' => $request->string('buscar')->trim()->value(),
            'cliente' => $request->string('cliente')->trim()->value(),
            'estado' => $request->string('estado')->trim()->value(),
            'mes' => $this->mesValido($request->string('mes')->trim()->value()),
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaContable(Viaje $viaje): array
    {
        return [
            // Las columnas de operación son las mismas de `/viajes`: acá no se
            // factura contra un resumen, se factura contra el viaje entero.
            ...$viaje->datosDeListado(),
            'gr_fisica_recibida_at' => $viaje->gr_fisica_recibida_at?->toIso8601String(),
            'estado' => $viaje->estadoCobranza()->value,
            'estado_label' => $viaje->estadoCobranza()->label(),
            'motivo_no_facturable' => $viaje->motivo_no_facturable,
            // Casi siempre una, pero el flete y la estadía pueden facturarse
            // por separado: la fila se abre en una línea por factura.
            'facturas' => $viaje->facturas
                ->map(fn (Factura $factura): array => $this->resumenFactura($factura))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenFactura(Factura $factura): array
    {
        return [
            'id' => $factura->id,
            'numero' => $factura->numero,
            'fecha_emision' => $factura->fecha_emision->toDateString(),
            // El flete descompuesto como lo imprime SUNAT; `monto` es el valor
            // sin IGV y el resto lo calcula el modelo.
            'monto' => $this->cifra($factura->monto),
            'igv' => $this->cifra($factura->igv),
            'total' => $this->cifra($factura->total),
            'detraccion' => $this->cifra($factura->detraccion),
            'neto' => $this->cifra($factura->neto),
            'moneda' => $factura->moneda->value,
            'simbolo' => $factura->moneda->simbolo(),
            'estado' => $factura->estado()->value,
            'fecha_vencimiento' => $factura->fechaVencimiento()->toDateString(),
            'fecha_pago' => $factura->fecha_pago?->toDateString(),
            'dias_vencida' => $factura->diasVencida(),
            'cuenta_bancaria_id' => $factura->cuenta_bancaria_id,
            'cuenta' => $factura->cuentaBancaria?->alias,
            'fecha_detraccion' => $factura->fecha_detraccion?->toDateString(),
            'constancia_detraccion' => $factura->constancia_detraccion,
            'observacion' => $factura->observacion,
            'archivo_url' => $factura->getFirstMediaUrl('archivo') ?: null,
            // Cuántos viajes cubre: la fila muestra el monto completo de la
            // factura, y sin esto parecería que cada una de las tres filas de
            // una factura agrupada cobró ese monto por separado.
            'viajes_count' => $factura->viajes->count(),
            // Los ids van con la fila para poder editar la factura sin otra
            // ida al servidor, y sobre todo sin perder los viajes que cayeron
            // en otra página del listado.
            'viaje_ids' => $factura->viajes->pluck('id')->all(),
        ];
    }

    /**
     * Las facturas que no cubren ninguna GR: subidas en PDF sin poder
     * asociarse solas, o que se quedaron sin viajes. La tabla se recorre por
     * viaje, así que sin esta bandeja no se verían en ningún lado.
     *
     * @return array<int, array<string, mixed>>
     */
    private function facturasPorAsociar(): array
    {
        return Factura::query()
            ->whereDoesntHave('viajes')
            ->with('media')
            ->orderBy('fecha_emision')
            ->get()
            ->map(function (Factura $factura): array {
                $citadas = $factura->gr_citadas ?? [];

                return [
                    'id' => $factura->id,
                    'numero' => $factura->numero,
                    'fecha_emision' => $factura->fecha_emision->toDateString(),
                    'cliente' => $factura->cliente_razon_social,
                    'cliente_ruc' => $factura->cliente_ruc,
                    'neto' => $this->cifra($factura->neto),
                    'simbolo' => $factura->moneda->simbolo(),
                    'archivo_url' => $factura->getFirstMediaUrl('archivo') ?: null,
                    'gr_citadas' => $citadas,
                    'periodo_desde' => $factura->periodo_desde?->toDateString(),
                    'periodo_hasta' => $factura->periodo_hasta?->toDateString(),
                    'motivo' => $this->motivoSinAsociar($factura, $citadas),
                    'candidatas' => $this->asociador->candidatas($factura),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Por qué la factura no se asoció sola, dicho para quien la va a resolver.
     *
     * @param  list<string>  $citadas
     */
    private function motivoSinAsociar(Factura $factura, array $citadas): string
    {
        if ($citadas !== []) {
            return 'Cita '.implode(', ', $citadas).': no están en el sistema o ya tienen otra factura.';
        }

        if ($factura->periodo_desde !== null && $factura->periodo_hasta !== null) {
            return 'No cita GR: cobra el período del '.$factura->periodo_desde->format('d/m').' al '.$factura->periodo_hasta->format('d/m/Y').'.';
        }

        return $factura->desde_pdf
            ? 'No cita ninguna GR.'
            : 'Se quedó sin GR.';
    }

    private function cifra(?string $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
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
