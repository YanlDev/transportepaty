<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreVehiculoRequest;
use App\Http\Requests\UpdateVehiculoRequest;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller
{
    /**
     * Los tractos son la unidad motriz y lo que más se consulta: listado
     * aparte para encontrarlos sin tener que filtrar entre las carretas.
     */
    public function tractos(Request $request): Response
    {
        return $this->listar($request, TipoVehiculo::Tracto);
    }

    /**
     * Las carretas se buscan por separado de los tractos por el mismo motivo:
     * son unidades distintas y mezclarlas en un solo listado obliga a filtrar
     * por tipo cada vez.
     */
    public function carretas(Request $request): Response
    {
        return $this->listar($request, TipoVehiculo::Carreta);
    }

    private function listar(Request $request, TipoVehiculo $tipo): Response
    {
        $this->authorize('viewAny', Vehiculo::class);

        // La caja solo tiene sentido para tractos: la carreta no tiene motor
        // y su columna `caja` siempre es nula (ver `Vehiculo::booted()`).
        $esTracto = $tipo === TipoVehiculo::Tracto;

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'estado' => $request->string('estado')->value(),
            'marca' => $request->string('marca')->value(),
            'caja' => $esTracto ? $request->string('caja')->value() : '',
        ];

        $vehiculos = Vehiculo::query()
            ->select([
                'id', 'placa', 'marca', 'modelo', 'anio', 'tipo',
                'estado', 'caja', 'color', 'ejes',
            ])
            ->where('tipo', $tipo->value)
            // El semáforo documental de cada fila se calcula sobre esta única
            // carga de documentos, sin una consulta por vehículo.
            ->with(['documentos:id,vehiculo_id,tipo,numero,fecha_vencimiento'])
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->wherePlacaLike($buscar)
                        ->orWhereLike('marca', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('modelo', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->when($filtros['estado'], fn ($query, string $estado) => $query->where('estado', $estado))
            ->when($filtros['marca'], fn ($query, string $marca) => $query->where('marca', $marca))
            ->when($filtros['caja'], fn ($query, string $caja) => $query->where('caja', $caja))
            ->orderBy('placa')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'marca' => $vehiculo->marca,
                'anio' => $vehiculo->anio,
                'tipo' => $vehiculo->tipo->value,
                'tipo_label' => $vehiculo->tipo->label(),
                'estado' => $vehiculo->estado->value,
                'caja' => $vehiculo->caja?->value,
                'caja_label' => $vehiculo->caja?->label(),
                'color' => $vehiculo->color,
                'ejes' => $vehiculo->ejes,
                'tuc_numero' => $vehiculo->tuc()?->numero,
                'documentacion' => $vehiculo->estadoDocumental(),
            ]);

        return Inertia::render('vehiculos/index', [
            'vehiculos' => $vehiculos,
            'filtros' => $filtros,
            'seccion' => $tipo->value,
            'estados' => EstadoVehiculo::options(),
            'marcas' => $this->opcionesMarca($tipo),
            'cajas' => $esTracto ? TipoCaja::options() : [],
        ]);
    }

    /**
     * Marcas realmente en uso para el tipo dado: no hay catálogo fijo de
     * marcas, así que el filtro se arma con lo que ya existe en la flota.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function opcionesMarca(TipoVehiculo $tipo): array
    {
        return Vehiculo::query()
            ->where('tipo', $tipo->value)
            ->whereNotNull('marca')
            ->distinct()
            ->orderBy('marca')
            ->pluck('marca')
            ->map(fn (string $marca): array => ['value' => $marca, 'label' => $marca])
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Vehiculo::class);

        // Llega de "Nuevo tracto"/"Nueva carreta" en el listado correspondiente,
        // para que el formulario arranque con el tipo correcto preseleccionado.
        $tipoInicial = TipoVehiculo::tryFrom($request->string('tipo')->value())
            ?? TipoVehiculo::Tracto;

        return Inertia::render('vehiculos/create', [
            'tipoInicial' => $tipoInicial->value,
            ...$this->datosFormulario(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehiculoRequest $request): RedirectResponse
    {
        $this->authorize('create', Vehiculo::class);

        $vehiculo = Vehiculo::create($request->validated());

        return to_route('vehiculos.show', $vehiculo)
            ->with('toast', ['type' => 'success', 'message' => 'Vehículo registrado correctamente.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehiculo $vehiculo): Response
    {
        $this->authorize('view', $vehiculo);

        $vehiculo->load('documentos.media');

        return Inertia::render('vehiculos/show', [
            'vehiculo' => $vehiculo,
            'documentacion' => $vehiculo->estadoDocumental(),
            'ranuras' => $vehiculo->ranurasDocumentales(),
            'tiposDocumento' => $this->tiposDocumento($vehiculo),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehiculo $vehiculo): Response
    {
        $this->authorize('update', $vehiculo);

        return Inertia::render('vehiculos/edit', [
            'vehiculo' => $vehiculo,
            ...$this->datosFormulario(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehiculoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('update', $vehiculo);

        $vehiculo->update($request->validated());

        return to_route('vehiculos.show', $vehiculo)
            ->with('toast', ['type' => 'success', 'message' => 'Vehículo actualizado correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('delete', $vehiculo);

        // Sin listado combinado al que volver: cada tipo vuelve al suyo.
        $ruta = $vehiculo->tipo === TipoVehiculo::Tracto ? 'tractos.index' : 'carretas.index';

        $vehiculo->delete();

        return to_route($ruta)
            ->with('toast', ['type' => 'success', 'message' => 'Vehículo eliminado correctamente.']);
    }

    /**
     * Tipos de documento que corresponden al vehículo. El SOAT solo aplica a
     * tractos, así que el formulario de la carreta no debe ofrecerlo.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function tiposDocumento(Vehiculo $vehiculo): array
    {
        return array_map(
            fn (TipoDocumento $tipo): array => [
                'value' => $tipo->value,
                'label' => $tipo->label(),
            ],
            $vehiculo->tipo->documentosAplicables(),
        );
    }

    /**
     * Shared data for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'tipos' => TipoVehiculo::options(),
            'cajas' => TipoCaja::options(),
            'estados' => EstadoVehiculo::options(),
        ];
    }
}
