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
     * Valor del filtro de caja para las unidades sin transmisión. La carreta es
     * remolcada, así que su columna `caja` queda nula y no la cubre ningún caso
     * de TipoCaja.
     */
    private const SIN_CAJA = 'sin_caja';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vehiculo::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'estado' => $request->string('estado')->value(),
            'tipo' => $request->string('tipo')->value(),
            'caja' => $request->string('caja')->value(),
        ];

        $vehiculos = Vehiculo::query()
            ->select([
                'id', 'placa', 'marca', 'modelo', 'anio', 'tipo',
                'estado', 'caja', 'color', 'ejes',
            ])
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
            ->when($filtros['tipo'], fn ($query, string $tipo) => $query->where('tipo', $tipo))
            ->when($filtros['caja'], fn ($query, string $caja) => $caja === self::SIN_CAJA
                ? $query->whereNull('caja')
                : $query->where('caja', $caja))
            // Los tractos primero: son la unidad motriz y lo que se busca al
            // entrar. Un orderBy plano pondría antes a las carretas, porque
            // ordena alfabéticamente y «carreta» precede a «tracto».
            ->orderByRaw('CASE WHEN tipo = ? THEN 0 ELSE 1 END', [TipoVehiculo::Tracto->value])
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
            'estados' => EstadoVehiculo::options(),
            'tipos' => TipoVehiculo::options(),
            'cajas' => $this->opcionesCaja(),
        ]);
    }

    /**
     * Opciones del filtro de caja: los tipos de transmisión más la pseudo-opción
     * para las unidades sin motor, que no existe como caso del enum.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function opcionesCaja(): array
    {
        return [
            ...TipoCaja::options(),
            ['value' => self::SIN_CAJA, 'label' => 'Sin caja (sin motor)'],
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Vehiculo::class);

        return Inertia::render('vehiculos/create', $this->datosFormulario());
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

        // Un fierro en una unidad vigente no se puede eliminar: la asignación
        // quedaría apuntando a un vehículo inexistente y el conductor seguiría
        // ocupado por una unidad fantasma.
        $enUso = $vehiculo->asignacionVigenteComoTracto()->exists()
            || $vehiculo->asignacionVigenteComoCarreta()->exists();

        if ($enUso) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "El vehículo {$vehiculo->placa} está en una unidad asignada. Libera la unidad antes de eliminarlo.",
            ]);
        }

        $vehiculo->delete();

        return to_route('vehiculos.index')
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
