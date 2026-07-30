<?php

namespace App\Http\Controllers;

use App\Enums\TipoCarga;
use App\Enums\TipoGuia;
use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreViajeRequest;
use App\Http\Requests\UpdateViajeRequest;
use App\Models\Conductor;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los tramos realmente hechos, con sus guías de remisión. Es el historial que
 * responde qué unidad llevó tal guía y qué guías hizo tal placa en un mes.
 */
class ViajeController extends Controller
{
    /** Muestra solo los viajes todavía en camino. */
    private const EN_CURSO = 'en_curso';

    /** Muestra solo los ya cerrados. */
    private const COMPLETADOS = 'completados';

    /**
     * @var list<string>
     */
    private const RELACIONES = [
        'tracto:id,placa',
        'carreta:id,placa',
        'conductor:id,nombres,apellidos',
        'origen:id,nombre',
        'destino:id,nombre',
        'media',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Viaje::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'estado' => $request->string('estado')->value() ?: 'todos',
            'carga' => $request->string('carga')->value(),
        ];

        $viajes = Viaje::query()
            ->with(self::RELACIONES)
            ->when($filtros['buscar'], fn (Builder $query, string $buscar) => $query
                ->where(fn (Builder $donde) => $donde
                    // Una guía se busca copiando el número de un correo, así que
                    // el mismo campo tiene que servir para guía, placa y chofer.
                    ->conGuia($buscar)
                    ->orWhereHas('tracto', fn (Builder $tracto) => $tracto
                        ->withoutGlobalScope(SoftDeletingScope::class)
                        ->wherePlacaLike($buscar))
                    ->orWhereHas('carreta', fn (Builder $carreta) => $carreta
                        ->withoutGlobalScope(SoftDeletingScope::class)
                        ->wherePlacaLike($buscar))
                    ->orWhereHas('conductor', fn (Builder $conductor) => $conductor
                        ->whereLike('nombres', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('apellidos', "%{$buscar}%", caseSensitive: false))))
            ->when($filtros['estado'] === self::EN_CURSO, fn (Builder $query) => $query->enCurso())
            ->when($filtros['estado'] === self::COMPLETADOS, fn (Builder $query) => $query->completados())
            ->when($filtros['carga'], fn (Builder $query, string $carga) => $query->where('tipo_carga', $carga))
            ->orderByDesc('fecha_salida')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Viaje $viaje): array => $this->comoItemDeLista($viaje));

        return Inertia::render('viajes/index', [
            'viajes' => $viajes,
            'filtros' => $filtros,
            'cargas' => TipoCarga::options(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Viaje::class);

        return Inertia::render('viajes/create', ['opciones' => $this->opciones()]);
    }

    public function store(StoreViajeRequest $request): RedirectResponse
    {
        $viaje = Viaje::query()->create($request->validated());

        return to_route('viajes.edit', $viaje)->with('toast', [
            'type' => 'success',
            'message' => "Viaje de {$viaje->tracto->placa} registrado. Ya puedes adjuntar sus guías.",
        ]);
    }

    public function edit(Viaje $viaje): Response
    {
        $this->authorize('view', $viaje);

        $viaje->load(self::RELACIONES);

        return Inertia::render('viajes/edit', [
            'viaje' => [
                'id' => $viaje->id,
                'tracto_id' => $viaje->tracto_id,
                'carreta_id' => $viaje->carreta_id,
                'conductor_id' => $viaje->conductor_id,
                'tipo_carga' => $viaje->tipo_carga->value,
                'origen_id' => $viaje->origen_id,
                'destino_id' => $viaje->destino_id,
                'fecha_salida' => $viaje->fecha_salida->toDateString(),
                'fecha_llegada' => $viaje->fecha_llegada?->toDateString(),
                'numero_guia_remitente' => $viaje->numero_guia_remitente,
                'numero_guia_transportista' => $viaje->numero_guia_transportista,
                'observaciones' => $viaje->observaciones,
                'tracto_placa' => $viaje->tracto->placa,
            ],
            'guias' => $this->guiasDe($viaje),
            'opciones' => $this->opciones(),
        ]);
    }

    public function update(UpdateViajeRequest $request, Viaje $viaje): RedirectResponse
    {
        $viaje->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Viaje actualizado.']);
    }

    public function destroy(Viaje $viaje): RedirectResponse
    {
        $this->authorize('delete', $viaje);

        $placa = $viaje->tracto->placa;
        $viaje->delete();

        return to_route('viajes.index')->with('toast', [
            'type' => 'success',
            'message' => "Se eliminó el viaje de {$placa}.",
        ]);
    }

    /**
     * Las dos guías del viaje, con su número y su archivo cuando ya llegaron.
     * Las que faltan van igual en la lista, como hueco a la vista.
     *
     * @return list<array<string, mixed>>
     */
    private function guiasDe(Viaje $viaje): array
    {
        return array_map(
            fn (TipoGuia $guia): array => $viaje->guiaComoArray($guia),
            TipoGuia::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'tractos' => $this->placasDe(TipoVehiculo::Tracto),
            'carretas' => $this->placasDe(TipoVehiculo::Carreta),
            'conductores' => Conductor::query()
                ->where('activo', true)
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get(['id', 'nombres', 'apellidos'])
                ->map(fn (Conductor $conductor): array => [
                    'value' => (string) $conductor->id,
                    'label' => $conductor->nombre_completo,
                ]),
            'ubicaciones' => Ubicacion::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (Ubicacion $ubicacion): array => [
                    'value' => (string) $ubicacion->id,
                    'label' => $ubicacion->nombre,
                ]),
            'cargas' => TipoCarga::options(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function placasDe(TipoVehiculo $tipo): array
    {
        $opciones = [];

        $vehiculos = Vehiculo::query()
            ->where('tipo', $tipo)
            ->orderBy('placa')
            ->get(['id', 'placa']);

        foreach ($vehiculos as $vehiculo) {
            $opciones[] = [
                'value' => (string) $vehiculo->id,
                'label' => $vehiculo->placa,
            ];
        }

        return $opciones;
    }

    /**
     * @return array<string, mixed>
     */
    private function comoItemDeLista(Viaje $viaje): array
    {
        return [
            'id' => $viaje->id,
            'tracto' => ['id' => $viaje->tracto->id, 'placa' => $viaje->tracto->placa],
            'carreta' => $viaje->carreta === null ? null : [
                'id' => $viaje->carreta->id,
                'placa' => $viaje->carreta->placa,
            ],
            'conductor' => $viaje->conductor?->nombre_completo,
            'tipo_carga_label' => $viaje->tipo_carga->label(),
            'fase_label' => $viaje->fase?->label(),
            'origen' => $viaje->origen->nombre,
            'destino' => $viaje->destino->nombre,
            'fecha_salida' => $viaje->fecha_salida->toDateString(),
            'fecha_llegada' => $viaje->fecha_llegada?->toDateString(),
            'en_curso' => $viaje->estaEnCurso(),
            'dias' => $viaje->duracionDias(),
            'guias' => $this->guiasDe($viaje),
            'observaciones' => $viaje->observaciones,
        ];
    }
}
