<?php

namespace App\Services;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\Permiso;
use App\Enums\RolBase;
use App\Models\MovimientoLlanta;
use App\Models\OrdenTrabajo;
use App\Models\ServicioMantenimiento;
use App\Models\TareaOrden;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * El recorrido de una orden de trabajo entre taller y sus mecánicos: a quién
 * se asigna, qué tareas lleva, y los pasos que cada uno da con su usuario
 * (empezar, terminar, observar, aprobar). Cada paso deja quién y cuándo.
 *
 * Las reglas viven acá y no en los controladores para que la pantalla web y
 * una futura app de mecánicos apliquen exactamente las mismas.
 *
 * La evidencia es parte del paso: no se empieza sin foto del antes ni se
 * termina sin foto del después.
 *
 * @phpstan-type Tarea array{id: int, descripcion: string, hecha: bool, hecha_por: string|null, hecha_at: string|null}
 * @phpstan-type Paso array{paso: string, usuario: string|null, cuando: string|null, nota: string|null}
 */
class EjecucionDeOrden
{
    /**
     * Quiénes pueden recibir órdenes: los usuarios activos con el permiso
     * de ejecutar. El admin lo tiene todo, pero no es mecánico.
     *
     * @return Collection<int, User>
     */
    public function mecanicosDisponibles(): Collection
    {
        return User::query()
            ->activos()
            ->permission(Permiso::MantenimientoEjecutar->value)
            ->whereDoesntHave('roles', fn ($roles) => $roles->where('name', RolBase::Admin->value))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Deja la orden con esos mecánicos. Si no se dice quién responde, es el
     * primero. Sin mecánicos, la orden la lleva taller como antes.
     *
     * @param  list<int>  $mecanicos
     */
    public function asignar(OrdenTrabajo $orden, array $mecanicos, ?int $responsable = null): void
    {
        $mecanicos = array_values(array_unique($mecanicos));
        $responsable = in_array($responsable, $mecanicos, true) ? $responsable : ($mecanicos[0] ?? null);

        $orden->mecanicos()->sync(array_fill_keys($mecanicos, ['responsable' => false]));

        if ($responsable !== null) {
            $orden->mecanicos()->updateExistingPivot($responsable, ['responsable' => true]);
        }
    }

    /**
     * Deja la lista de tareas como llega del formulario: las que traen `id`
     * se conservan (con su marca de hecha), las nuevas se agregan y las que
     * ya no están se quitan.
     *
     * @param  list<array{id?: int|null, descripcion: string}>  $tareas
     */
    public function guardarTareas(OrdenTrabajo $orden, array $tareas): void
    {
        $existentes = $orden->tareas()->get()->keyBy('id');
        $conservadas = [];

        foreach (array_values($tareas) as $posicion => $tarea) {
            $descripcion = trim($tarea['descripcion']);
            $actual = $existentes->get($tarea['id'] ?? null);

            if ($actual instanceof TareaOrden) {
                $actual->update(['descripcion' => $descripcion, 'posicion' => $posicion]);
                $conservadas[] = $actual->id;

                continue;
            }

            $conservadas[] = $orden->tareas()->create(['descripcion' => $descripcion, 'posicion' => $posicion])->id;
        }

        $orden->tareas()->whereNotIn('id', $conservadas)->delete();
    }

    /**
     * Lo que pide un servicio del plan (con los niveles que contiene), como
     * tareas: es la lista con la que nace una preventiva.
     *
     * @return list<array{descripcion: string}>
     */
    public function tareasDelPlan(ServicioMantenimiento $servicio): array
    {
        return ServicioMantenimiento::query()
            ->where('plan_mantenimiento_id', $servicio->plan_mantenimiento_id)
            ->where('nivel', '<=', $servicio->nivel)
            ->with('repuestos')
            ->orderBy('nivel')
            ->get()
            ->flatMap(fn (ServicioMantenimiento $nivel) => $nivel->repuestos->map(
                fn ($repuesto): array => ['descripcion' => mb_substr((string) $repuesto->descripcion, 0, 500)],
            ))
            ->filter(fn (array $tarea): bool => $tarea['descripcion'] !== '')
            ->values()
            ->all();
    }

    /**
     * El mecánico empieza: la orden tiene que estar aceptada y con al menos
     * una foto de cómo llegó la unidad.
     */
    public function iniciar(OrdenTrabajo $orden, User $mecanico, ?int $kilometraje = null): void
    {
        if ($orden->estado !== EstadoOrdenTrabajo::Pendiente) {
            $this->rechazar('estado', "La {$orden->numero()} está ".mb_strtolower($orden->estado->label()).': no se puede empezar.');
        }

        if ($orden->getMedia('antes')->isEmpty()) {
            $this->rechazar('fotos', 'Toma al menos una foto de cómo llegó la unidad antes de empezar.');
        }

        $orden->update([
            'estado' => EstadoOrdenTrabajo::EnProceso,
            'inicio' => now(),
            'iniciado_por' => $mecanico->id,
            'kilometraje' => $kilometraje ?? $orden->kilometraje,
        ]);
    }

    /**
     * El mecánico termina: con foto de cómo quedó y, si dejó tareas sin
     * marcar, diciendo por qué. La orden pasa a taller para su revisión.
     */
    public function terminar(OrdenTrabajo $orden, User $mecanico, ?string $nota = null, ?int $kilometraje = null): void
    {
        if (! $orden->estado->enEjecucion()) {
            $this->rechazar('estado', "La {$orden->numero()} está ".mb_strtolower($orden->estado->label()).': no se puede terminar.');
        }

        if ($orden->getMedia('despues')->isEmpty()) {
            $this->rechazar('fotos', 'Toma al menos una foto de cómo quedó el trabajo antes de terminar.');
        }

        $sinHacer = $orden->tareas()->whereNull('hecha_at')->count();

        if ($sinHacer > 0 && blank($nota)) {
            $this->rechazar('nota', $sinHacer === 1
                ? 'Queda una tarea sin marcar: márcala o explica por qué no se hizo.'
                : "Quedan {$sinHacer} tareas sin marcar: márcalas o explica por qué no se hicieron.");
        }

        $orden->update([
            'estado' => EstadoOrdenTrabajo::PorRevisar,
            'termino' => now(),
            'terminado_por' => $mecanico->id,
            'nota_mecanico' => filled($nota) ? $nota : $orden->nota_mecanico,
            'kilometraje' => $kilometraje ?? $orden->kilometraje,
        ]);
    }

    /**
     * Taller no la aprueba: vuelve al mecánico con lo que hay que corregir.
     */
    public function observar(OrdenTrabajo $orden, User $revisor, string $motivo): void
    {
        if ($orden->estado !== EstadoOrdenTrabajo::PorRevisar) {
            $this->rechazar('motivo', "La {$orden->numero()} no está por revisar.");
        }

        $orden->update([
            'estado' => EstadoOrdenTrabajo::Observada,
            'revisado_por' => $revisor->id,
            'revisado_at' => now(),
            'observacion_revision' => $motivo,
        ]);
    }

    /**
     * Marca o desmarca una tarea, con quién la hizo.
     */
    public function marcarTarea(TareaOrden $tarea, User $mecanico, bool $hecha): void
    {
        if (! $tarea->orden->estado->enEjecucion()) {
            $this->rechazar('tarea', 'Las tareas se marcan con la orden en proceso: empiézala primero.');
        }

        $tarea->update([
            'hecha_at' => $hecha ? now() : null,
            'hecha_por' => $hecha ? $mecanico->id : null,
        ]);
    }

    /**
     * Todo lo que se ve de la ejecución de una orden, igual para el mecánico
     * y para taller: quiénes la llevan, las tareas, las fotos, los pasos con
     * su usuario y las llantas que se movieron mientras estuvo abierta.
     *
     * @return array<string, mixed>
     */
    public function vista(OrdenTrabajo $orden): array
    {
        $orden->loadMissing([
            'mecanicos:id,name', 'tareas.hechaPor:id,name', 'media',
            'creador:id,name', 'iniciadoPor:id,name', 'terminadoPor:id,name', 'revisadoPor:id,name',
        ]);

        return [
            'mecanicos' => $orden->mecanicos
                ->map(fn (User $mecanico): array => [
                    'id' => $mecanico->id,
                    'nombre' => $mecanico->name,
                    'responsable' => (bool) $mecanico->pivot->responsable,
                ])
                ->values()
                ->all(),
            'tareas' => $orden->tareas
                ->map(fn (TareaOrden $tarea): array => [
                    'id' => $tarea->id,
                    'descripcion' => $tarea->descripcion,
                    'hecha' => $tarea->estaHecha(),
                    'hecha_por' => $tarea->hechaPor?->name,
                    'hecha_at' => $tarea->hecha_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'fotos' => $orden->media
                ->map(fn (Media $foto): array => [
                    'id' => $foto->id,
                    'coleccion' => $foto->collection_name,
                    'url' => $foto->getUrl(),
                ])
                ->values()
                ->all(),
            'pasos' => $this->pasos($orden),
            'llantas' => $this->llantas($orden),
            'nota_mecanico' => $orden->nota_mecanico,
            'observacion_revision' => $orden->observacion_revision,
        ];
    }

    /**
     * La línea de tiempo: cada paso dado, con el usuario que lo dio.
     *
     * @return list<Paso>
     */
    private function pasos(OrdenTrabajo $orden): array
    {
        $solicitada = $orden->estado === EstadoOrdenTrabajo::Solicitada;
        $revisada = $orden->revisado_at !== null;

        return array_values(array_filter([
            [
                'paso' => $solicitada ? 'Solicitada' : 'Registrada',
                'usuario' => $orden->creador?->name,
                'cuando' => $orden->created_at?->toIso8601String(),
                'nota' => null,
            ],
            $orden->inicio === null ? null : [
                'paso' => 'Empezó el trabajo',
                'usuario' => $orden->iniciadoPor?->name,
                'cuando' => $orden->inicio->toIso8601String(),
                'nota' => null,
            ],
            $orden->termino === null ? null : [
                'paso' => 'Terminó el trabajo',
                'usuario' => $orden->terminadoPor?->name,
                'cuando' => $orden->termino->toIso8601String(),
                'nota' => $orden->nota_mecanico,
            ],
            ! $revisada ? null : [
                'paso' => $orden->estado === EstadoOrdenTrabajo::Finalizada ? 'Taller la finalizó' : 'Taller la observó',
                'usuario' => $orden->revisadoPor?->name,
                'cuando' => $orden->revisado_at?->toIso8601String(),
                'nota' => $orden->estado === EstadoOrdenTrabajo::Finalizada ? null : $orden->observacion_revision,
            ],
        ]));
    }

    /**
     * Los movimientos de llantas de la unidad mientras la orden estuvo
     * abierta. No se guardan en la orden: se deducen por unidad y fecha,
     * como los materiales se deducen de los vales.
     *
     * @return list<array{id: int, tipo: string, posicion: string, posicion_destino: string|null, fecha: string, llanta: string|null, usuario: string|null}>
     */
    private function llantas(OrdenTrabajo $orden): array
    {
        if ($orden->estado === EstadoOrdenTrabajo::Solicitada) {
            return [];
        }

        $desde = ($orden->inicio ?? $orden->fecha)->toDateString();
        $desde = min($desde, $orden->fecha->toDateString());
        $hasta = ($orden->termino ?? Carbon::now())->toDateString();

        return array_values(MovimientoLlanta::query()
            ->where('vehiculo_id', $orden->vehiculo_id)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', max($desde, $hasta))
            ->with(['llanta:id,marca,medida,codigo', 'user:id,name'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (MovimientoLlanta $movimiento): array => [
                'id' => $movimiento->id,
                'tipo' => $movimiento->tipo->label(),
                'posicion' => $movimiento->posicion,
                'posicion_destino' => $movimiento->posicion_destino,
                'fecha' => $movimiento->fecha->toDateString(),
                'llanta' => trim(implode(' ', array_filter([$movimiento->llanta?->marca, $movimiento->llanta?->medida, $movimiento->llanta?->codigo]))) ?: null,
                'usuario' => $movimiento->user?->name,
            ])
            ->all());
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
