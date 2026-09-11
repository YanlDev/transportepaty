<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCotizacion;
use App\Http\Requests\CotizacionRequest;
use App\Http\Requests\PrevisualizarCotizacionRequest;
use App\Models\Cliente;
use App\Models\ComponenteCosto;
use App\Models\Cotizacion;
use App\Models\ParametroFlota;
use App\Models\PuntoTraslado;
use App\Services\CalculadoraCotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las tarifas que se le pasan a un cliente antes de mover la unidad. Hasta
 * ahora esto vivía en un Excel por ruta; acá el cálculo es el mismo pero la
 * estructura de costos sale de un solo lugar (`ComponenteCosto`) y queda
 * registro de qué se cotizó, a quién y con qué números.
 */
class CotizacionController extends Controller
{
    public function __construct(private readonly CalculadoraCotizacion $calculadora) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cotizacion::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'estado' => $request->string('estado')->trim()->value() ?: null,
        ];

        $cotizaciones = Cotizacion::query()
            ->when($filtros['buscar'], fn ($query, string $buscar) => $query->buscar($buscar))
            ->when($filtros['estado'], fn ($query, string $estado) => $query->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Cotizacion $cotizacion): array => [
                'id' => $cotizacion->id,
                'numero' => $cotizacion->numero,
                'fecha' => $cotizacion->fecha->toDateString(),
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'origen' => $cotizacion->origen,
                'destino' => $cotizacion->destino,
                'km' => $cotizacion->km,
                'dias' => $cotizacion->dias,
                'costo_por_km' => round($cotizacion->costoPorKm(), 2),
                'total' => $cotizacion->total,
                'estado' => $cotizacion->estado->value,
                'estado_label' => $cotizacion->estado->label(),
            ]);

        return Inertia::render('cotizaciones/index', [
            'cotizaciones' => $cotizaciones,
            'filtros' => $filtros,
            'estados' => EstadoCotizacion::options(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Cotizacion::class);

        return Inertia::render('cotizaciones/create', $this->opcionesFormulario());
    }

    public function store(CotizacionRequest $request): RedirectResponse
    {
        $this->authorize('create', Cotizacion::class);

        $datos = $request->validated();
        $flota = ParametroFlota::vigentes();
        $calculo = $this->calculadora->calcular($datos, $this->lineasVigentes($flota), $flota->igv_pct);

        $cotizacion = Cotizacion::create([
            ...$datos,
            ...$calculo,
            'numero' => Cotizacion::siguienteNumero(),
        ]);

        return to_route('cotizaciones.show', $cotizacion)->with('toast', [
            'type' => 'success',
            'message' => "Cotización {$cotizacion->numero} creada.",
        ]);
    }

    public function show(Cotizacion $cotizacion): Response
    {
        $this->authorize('view', $cotizacion);

        return Inertia::render('cotizaciones/show', [
            'cotizacion' => $this->paraLaVista($cotizacion),
        ]);
    }

    public function edit(Cotizacion $cotizacion): Response
    {
        $this->authorize('update', $cotizacion);

        return Inertia::render('cotizaciones/edit', [
            'cotizacion' => $this->paraLaVista($cotizacion),
            ...$this->opcionesFormulario(),
        ]);
    }

    /**
     * Al editar se recalcula con las tasas que la cotización ya tenía, no con
     * las vigentes: corregir un kilometraje mal tipeado no debería cambiarle
     * el precio a una proforma que el cliente ya tiene en la mano. Si lo que
     * se quiere es reprecificar con los costos de hoy, se emite una nueva.
     */
    public function update(CotizacionRequest $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->authorize('update', $cotizacion);

        $datos = $request->validated();
        $calculo = $this->calculadora->calcular(
            $datos,
            $cotizacion->lineasCongeladas(),
            ParametroFlota::vigentes()->igv_pct,
        );

        $cotizacion->update([...$datos, ...$calculo]);

        return to_route('cotizaciones.show', $cotizacion)->with('toast', [
            'type' => 'success',
            'message' => 'Cotización actualizada.',
        ]);
    }

    public function destroy(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->authorize('delete', $cotizacion);

        $cotizacion->delete();

        return to_route('cotizaciones.index', $request->query())->with('toast', [
            'type' => 'success',
            'message' => 'Cotización eliminada.',
        ]);
    }

    /**
     * El desglose de una ruta que todavía no se guardó, para que el formulario
     * muestre la tarifa mientras se completa. Vive en el servidor —y no
     * repetido en el frontend— para que haya una sola fórmula.
     */
    public function previsualizar(PrevisualizarCotizacionRequest $request): JsonResponse
    {
        $this->authorize('create', Cotizacion::class);

        $datos = $request->validated();

        $flota = ParametroFlota::vigentes();
        $calculo = $this->calculadora->calcular($datos, $this->lineasVigentes($flota), $flota->igv_pct);

        return response()->json([
            ...$calculo,
            'costo_por_km' => $datos['km'] > 0
                ? round($calculo['costo_operativo'] / $datos['km'], 4)
                : 0,
        ]);
    }

    /**
     * La proforma tal como se le manda al cliente, con el mismo formato que ya
     * se venía usando en papel.
     */
    public function pdf(Cotizacion $cotizacion): HttpResponse
    {
        $this->authorize('view', $cotizacion);

        return Pdf::loadView('pdf.cotizacion', ['cotizacion' => $cotizacion])
            ->download("proforma-{$cotizacion->numero}.pdf");
    }

    /**
     * @return list<array{nombre: string, tipo: string, naturaleza: string, tasa: float}>
     */
    private function lineasVigentes(ParametroFlota $flota): array
    {
        return $this->calculadora->lineasDesde(
            ComponenteCosto::query()->activos()->ordenados()->get(),
            $flota,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paraLaVista(Cotizacion $cotizacion): array
    {
        return [
            ...$cotizacion->only([
                'id', 'numero', 'cliente_id', 'cliente_nombre', 'cliente_ruc',
                'punto_partida_id', 'punto_llegada_id', 'origen', 'destino',
                'material', 'km', 'dias', 'peajes', 'viaticos', 'alojamiento',
                'cochera', 'carga_descarga', 'otros_ruta', 'desglose',
                'margen_pct', 'total_directo', 'total_indirecto',
                'costo_operativo', 'margen', 'subtotal', 'igv', 'total', 'notas',
            ]),
            'fecha' => $cotizacion->fecha->toDateString(),
            'valido_hasta' => $cotizacion->valido_hasta->toDateString(),
            'estado' => $cotizacion->estado->value,
            'estado_label' => $cotizacion->estado->label(),
            'costo_por_km' => round($cotizacion->costoPorKm(), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opcionesFormulario(): array
    {
        $flota = ParametroFlota::vigentes();

        return [
            'clientes' => Cliente::query()
                ->where('activo', true)
                ->orderBy('alias')
                ->get(['id', 'alias', 'razon_social', 'ruc'])
                ->all(),
            'puntos' => PuntoTraslado::query()
                ->activos()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'direccion'])
                ->all(),
            'estados' => EstadoCotizacion::options(),
            'flota' => [
                'viatico_dia' => $flota->viatico_dia,
                'margen_pct_default' => $flota->margen_pct_default,
                'igv_pct' => $flota->igv_pct,
            ],
        ];
    }
}
