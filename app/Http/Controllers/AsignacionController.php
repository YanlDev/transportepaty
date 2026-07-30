<?php

namespace App\Http\Controllers;

use App\Enums\SemaforoDocumental;
use App\Enums\TipoCaja;
use App\Enums\TipoVehiculo;
use App\Http\Requests\ReasignarAsignacionRequest;
use App\Http\Requests\StoreAsignacionRequest;
use App\Http\Requests\UpdateAsignacionRequest;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AsignacionController extends Controller
{
    /** Muestra solo las unidades armadas hoy. */
    private const VIGENTES = 'vigentes';

    /** Muestra solo las asignaciones ya cerradas. */
    private const HISTORIAL = 'historial';

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asignacion::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
            'estado' => $request->string('estado')->value() ?: self::VIGENTES,
            'caja' => $request->string('caja')->value(),
        ];

        $asignaciones = Asignacion::query()
            ->with([
                'conductor:id,nombres,apellidos,telefono',
                'tracto:id,placa,marca,estado,tipo,caja',
                'tracto.documentos:id,vehiculo_id,tipo,numero,fecha_vencimiento',
                'carreta:id,placa,tipo',
                'carreta.documentos:id,vehiculo_id,tipo,numero,fecha_vencimiento',
            ])
            ->when($filtros['buscar'], function (Builder $query, string $buscar): void {
                $query->where(function (Builder $query) use ($buscar): void {
                    // Sin el scope de soft deletes: el historial debe poder
                    // buscarse también por placas de fierros que ya salieron
                    // de la flota.
                    $query->whereHas('conductor', fn (Builder $conductor) => $conductor
                        ->whereLike('nombres', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('apellidos', "%{$buscar}%", caseSensitive: false))
                        ->orWhereHas('tracto', fn (Builder $tracto) => $tracto
                            ->withoutGlobalScope(SoftDeletingScope::class)
                            ->wherePlacaLike($buscar))
                        ->orWhereHas('carreta', fn (Builder $carreta) => $carreta
                            ->withoutGlobalScope(SoftDeletingScope::class)
                            ->wherePlacaLike($buscar));
                });
            })
            ->when($filtros['estado'] === self::VIGENTES, fn (Builder $query) => $query->vigentes())
            ->when($filtros['estado'] === self::HISTORIAL, fn (Builder $query) => $query->finalizadas())
            // La caja es del tracto: la carreta es remolcada y no tiene.
            ->when($filtros['caja'], fn (Builder $query, string $caja) => $query
                ->whereHas('tracto', fn (Builder $tracto) => $tracto
                    ->withoutGlobalScope(SoftDeletingScope::class)
                    ->where('caja', $caja)))
            ->orderByRaw('hasta is null desc')
            ->orderByDesc('desde')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through($this->comoItemDeLista(...));

        return Inertia::render('asignaciones/index', [
            'asignaciones' => $asignaciones,
            'filtros' => $filtros,
            'cajas' => TipoCaja::options(),
        ]);
    }

    /**
     * Lo que está parado: tractos sin conductor, carretas sin enganchar y
     * conductores sin unidad, cada grupo por separado. Vive fuera del listado de
     * asignaciones porque responde a otra pregunta —qué me falta mover— y
     * mezclarlo con las unidades armadas obligaba a leer dos cosas a la vez.
     */
    public function disponibles(): Response
    {
        $this->authorize('viewAny', Asignacion::class);

        $tractos = Vehiculo::query()
            ->sinAsignar(TipoVehiculo::Tracto)
            ->with('documentos:id,vehiculo_id,tipo,numero,fecha_vencimiento')
            ->orderBy('placa')
            ->get();

        $carretas = Vehiculo::query()
            ->sinAsignar(TipoVehiculo::Carreta)
            ->with('documentos:id,vehiculo_id,tipo,fecha_vencimiento')
            ->orderBy('placa')
            ->get();

        $conductores = Conductor::query()
            ->sinAsignar()
            ->with('documentos:id,conductor_id,tipo,fecha_vencimiento')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        return Inertia::render('asignaciones/disponibles', [
            'tractos' => $tractos->map(fn (Vehiculo $tracto): array => [
                'id' => $tracto->id,
                'placa' => $tracto->placa,
                'marca' => $tracto->marca,
                'tuc_numero' => $tracto->tuc()?->numero,
                'estado' => $tracto->estado->value,
                'caja_label' => $tracto->caja?->label(),
                'documentacion' => $tracto->estadoDocumental(),
            ])->values(),
            'carretas' => $carretas->map(fn (Vehiculo $carreta): array => [
                'id' => $carreta->id,
                'placa' => $carreta->placa,
                'marca' => $carreta->marca,
                'estado' => $carreta->estado->value,
                'documentacion' => $carreta->estadoDocumental(),
            ])->values(),
            'conductores' => $conductores->map(fn (Conductor $conductor): array => [
                'id' => $conductor->id,
                'nombre_completo' => $conductor->nombre_completo,
                'telefono' => $conductor->telefono,
                'licencia' => $conductor->licencia,
                'categoria_licencia' => $conductor->categoria_licencia,
                'documentacion' => $conductor->estadoDocumental(),
            ])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Asignacion::class);

        return Inertia::render('asignaciones/create', [
            ...$this->opcionesFormulario(),
            // Se llega aquí desde «Sin asignar» o tras liberar una unidad, con
            // el fierro o el conductor ya elegido para no buscarlo de nuevo.
            'preseleccion' => [
                'tracto_id' => $request->integer('tracto') ?: null,
                'carreta_id' => $request->integer('carreta') ?: null,
                'conductor_id' => $request->integer('conductor') ?: null,
            ],
        ]);
    }

    public function store(StoreAsignacionRequest $request): RedirectResponse
    {
        $this->authorize('create', Asignacion::class);

        Asignacion::create([
            ...$request->validated(),
            'desde' => now()->toDateString(),
        ]);

        return to_route('asignaciones.index')
            ->with('toast', ['type' => 'success', 'message' => 'Unidad asignada correctamente.']);
    }

    public function edit(Asignacion $asignacion): Response
    {
        $this->authorize('update', $asignacion);
        $this->soloVigentes($asignacion);

        $asignacion->load('tracto:id,placa');

        return Inertia::render('asignaciones/edit', [
            'asignacion' => [
                'id' => $asignacion->id,
                'conductor_id' => $asignacion->conductor_id,
                'tracto_id' => $asignacion->tracto_id,
                'tracto_placa' => $asignacion->tracto->placa,
                'carreta_id' => $asignacion->carreta_id,
                'desde' => $asignacion->desde->toDateString(),
                'observaciones' => $asignacion->observaciones,
            ],
            ...$this->opcionesFormulario($asignacion),
        ]);
    }

    public function update(UpdateAsignacionRequest $request, Asignacion $asignacion): RedirectResponse
    {
        $this->authorize('update', $asignacion);
        $this->soloVigentes($asignacion);

        $asignacion->update($request->validated());

        return to_route('asignaciones.index')
            ->with('toast', ['type' => 'success', 'message' => 'Asignación actualizada correctamente.']);
    }

    /**
     * Formulario para mover al conductor de esta asignación a otro tracto (y,
     * si hace falta, otra carreta) sin pasar por liberar y crear por separado.
     */
    public function formularioReasignar(Asignacion $asignacion): Response
    {
        $this->authorize('update', $asignacion);
        $this->soloVigentes($asignacion);

        $asignacion->load(['conductor:id,nombres,apellidos', 'tracto:id,placa', 'carreta:id,placa']);

        return Inertia::render('asignaciones/reasignar', [
            'asignacion' => [
                'id' => $asignacion->id,
                'conductor_nombre' => $asignacion->conductor->nombre_completo,
                'tracto_placa' => $asignacion->tracto->placa,
                'carreta_placa' => $asignacion->carreta?->placa,
            ],
            ...$this->opcionesFormulario($asignacion),
        ]);
    }

    /**
     * Mueve al conductor a otro tracto en un solo paso: cierra la asignación
     * vigente y abre una nueva con el mismo conductor, sin el ida y vuelta de
     * liberar primero y armar la unidad después. El conductor no se toca —es
     * la misma persona que sigue manejando— solo cambia el fierro.
     */
    public function reasignar(ReasignarAsignacionRequest $request, Asignacion $asignacion): RedirectResponse
    {
        $this->authorize('update', $asignacion);
        $this->soloVigentes($asignacion);

        $tractoAnterior = $asignacion->tracto->placa;

        $nueva = DB::transaction(function () use ($asignacion, $request): Asignacion {
            // Libera primero: el índice único de asignaciones vigentes por
            // conductor exige que esta fila deje de contar antes de abrir la
            // siguiente.
            $asignacion->liberar();

            return Asignacion::create([
                'conductor_id' => $asignacion->conductor_id,
                'tracto_id' => $request->integer('tracto_id'),
                'carreta_id' => $request->integer('carreta_id') ?: null,
                'desde' => now()->toDateString(),
                'observaciones' => $request->string('observaciones')->value() ?: null,
            ]);
        });

        return to_route('asignaciones.index')->with('toast', [
            'type' => 'success',
            'message' => "{$asignacion->conductor->nombre_completo} pasó de {$tractoAnterior} a {$nueva->tracto->placa}.",
        ]);
    }

    /**
     * Cierra la asignación con la fecha de hoy. No borra nada: el conductor y
     * los fierros quedan libres y la unidad pasa al historial.
     */
    public function liberar(Asignacion $asignacion): RedirectResponse
    {
        $this->authorize('update', $asignacion);
        $this->soloVigentes($asignacion);

        $asignacion->liberar();

        $placa = $asignacion->tracto->placa;

        return to_route('asignaciones.create', ['tracto' => $asignacion->tracto_id])
            ->with('toast', [
                'type' => 'success',
                'message' => "Unidad liberada. El tracto {$placa} y su conductor quedan disponibles.",
            ]);
    }

    /**
     * Solo para deshacer un registro equivocado. Borrar una asignación real
     * elimina su rastro del historial, así que lo normal es liberarla.
     */
    public function destroy(Asignacion $asignacion): RedirectResponse
    {
        $this->authorize('delete', $asignacion);

        $asignacion->delete();

        return to_route('asignaciones.index')
            ->with('toast', ['type' => 'success', 'message' => 'Asignación eliminada correctamente.']);
    }

    /**
     * El historial es un registro de lo que pasó; se consulta, no se corrige.
     */
    private function soloVigentes(Asignacion $asignacion): void
    {
        abort_unless($asignacion->estaVigente(), 403, 'Esta asignación ya fue cerrada.');
    }

    /**
     * @return array{id: int, conductor: array{id: int, nombre_completo: string, telefono: string|null}, tracto: array{id: int, placa: string, marca: string|null, tuc_numero: string|null}, carreta: array{id: int, placa: string, tuc_numero: string|null}|null, desde: string, hasta: string|null, observaciones: string|null, vigente: bool, documentacion: array{semaforo: string, faltantes: list<string>, vencidos: list<string>, por_vencer: list<string>}}
     */
    private function comoItemDeLista(Asignacion $asignacion): array
    {
        return [
            'id' => $asignacion->id,
            'conductor' => [
                'id' => $asignacion->conductor->id,
                'nombre_completo' => $asignacion->conductor->nombre_completo,
                'telefono' => $asignacion->conductor->telefono,
            ],
            'tracto' => [
                'id' => $asignacion->tracto->id,
                'placa' => $asignacion->tracto->placa,
                'marca' => $asignacion->tracto->marca,
                'tuc_numero' => $asignacion->tracto->tuc()?->numero,
            ],
            'carreta' => $asignacion->carreta === null ? null : [
                'id' => $asignacion->carreta->id,
                'placa' => $asignacion->carreta->placa,
                'tuc_numero' => $asignacion->carreta->tuc()?->numero,
            ],
            'desde' => $asignacion->desde->toDateString(),
            'hasta' => $asignacion->hasta?->toDateString(),
            'observaciones' => $asignacion->observaciones,
            'vigente' => $asignacion->estaVigente(),
            'documentacion' => $this->documentacionDeLaUnidad($asignacion),
        ];
    }

    /**
     * Resume en una sola luz la documentación del tracto y la carreta. Manda el
     * peor de los dos: si a la carreta le falta el TUC, la unidad completa no
     * puede salir aunque el tracto esté impecable.
     *
     * @return array{semaforo: string, faltantes: list<string>, vencidos: list<string>, por_vencer: list<string>}
     */
    private function documentacionDeLaUnidad(Asignacion $asignacion): array
    {
        $porFierro = ['Tracto' => $asignacion->tracto->estadoDocumental()];

        if ($asignacion->carreta !== null) {
            $porFierro['Carreta'] = $asignacion->carreta->estadoDocumental();
        }

        $semaforo = SemaforoDocumental::Verde;
        $combinado = ['faltantes' => [], 'vencidos' => [], 'por_vencer' => []];

        foreach ($porFierro as $fierro => $estado) {
            $semaforo = $semaforo->peorQue(SemaforoDocumental::from($estado['semaforo']));

            foreach (array_keys($combinado) as $grupo) {
                foreach ($estado[$grupo] as $documento) {
                    $combinado[$grupo][] = "{$fierro}: {$documento}";
                }
            }
        }

        return ['semaforo' => $semaforo->value, ...$combinado];
    }

    /**
     * Opciones de los selectores. Solo ofrece conductores y fierros libres para
     * que no se pueda armar una unidad con algo que ya está en la calle; al
     * editar se suman los que la asignación ya tiene ocupados.
     *
     * @return array<string, mixed>
     */
    private function opcionesFormulario(?Asignacion $asignacion = null): array
    {
        return [
            'conductores' => $this->conductoresLibres($asignacion)
                ->map(fn (Conductor $conductor): array => [
                    'id' => $conductor->id,
                    'nombre_completo' => $conductor->nombre_completo,
                    'telefono' => $conductor->telefono,
                ])
                ->values(),
            'tractos' => $this->vehiculosLibres(TipoVehiculo::Tracto, $asignacion)
                ->map($this->comoOpcionDeVehiculo(...))
                ->values(),
            'carretas' => $this->vehiculosLibres(TipoVehiculo::Carreta, $asignacion)
                ->map($this->comoOpcionDeVehiculo(...))
                ->values(),
        ];
    }

    /**
     * @return array{id: int, placa: string, descripcion: string}
     */
    private function comoOpcionDeVehiculo(Vehiculo $vehiculo): array
    {
        return [
            'id' => $vehiculo->id,
            'placa' => $vehiculo->placa,
            'descripcion' => $vehiculo->descripcion(),
        ];
    }

    /**
     * @return Collection<int, Conductor>
     */
    private function conductoresLibres(?Asignacion $asignacion = null): Collection
    {
        return Conductor::query()
            ->where(function (Builder $query) use ($asignacion): void {
                $query->sinAsignar();

                // Al editar, el conductor que la asignación ya tiene sigue
                // siendo una opción válida aunque no esté «libre».
                if ($asignacion !== null) {
                    $query->orWhere('id', $asignacion->conductor_id);
                }
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'telefono']);
    }

    /**
     * @return Collection<int, Vehiculo>
     */
    private function vehiculosLibres(TipoVehiculo $tipo, ?Asignacion $asignacion = null): Collection
    {
        $yaAsignado = $tipo === TipoVehiculo::Tracto
            ? $asignacion?->tracto_id
            : $asignacion?->carreta_id;

        return Vehiculo::query()
            ->where(function (Builder $query) use ($tipo, $yaAsignado): void {
                $query->sinAsignar($tipo);

                // Al editar, el fierro que la asignación ya tiene sigue siendo
                // una opción válida aunque no esté «libre».
                if ($yaAsignado !== null) {
                    $query->orWhere('id', $yaAsignado);
                }
            })
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca', 'modelo']);
    }
}
