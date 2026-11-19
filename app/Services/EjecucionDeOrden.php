<?php

namespace App\Services;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\Permiso;
use App\Enums\RolBase;
use App\Models\Articulo;
use App\Models\MovimientoLlanta;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use App\Models\ServicioMantenimiento;
use App\Models\TareaOrden;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * El recorrido de una orden de trabajo entre operaciones y sus mecánicos:
 * operaciones crea la pre-orden y elige al mecánico; el mecánico arma el
 * requerimiento (tareas, repuestos, fotos del antes) y lo envía; operaciones
 * lo aprueba y nace la orden; el mecánico la ejecuta y operaciones la
 * valida. Cada paso deja quién y cuándo.
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
    public function __construct(private readonly MaterialesDeOrden $materiales) {}

    /**
     * El catálogo del almacén para pedir repuestos por su nombre: sin
     * costos, solo si hay. «Hay» es lo que queda libre: lo apartado para
     * otras órdenes ya aprobadas no cuenta.
     *
     * @return list<array{id: int, nombre: string, codigo: string, unidad: string, hay: bool}>
     */
    public function articulosPedibles(): array
    {
        $reservado = app(RepuestosDeOrden::class)->reservado();

        return Articulo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(1000)
            ->get(['id', 'codigo', 'nombre', 'unidad', 'stock'])
            ->map(fn (Articulo $articulo): array => [
                'id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'codigo' => $articulo->codigo,
                'unidad' => $articulo->unidad->value,
                'hay' => (float) $articulo->stock - ($reservado[$articulo->id]['cantidad'] ?? 0) > 0.0005,
            ])
            ->all();
    }

    /**
     * El mecánico suma una tarea a su requerimiento.
     */
    public function agregarTarea(OrdenTrabajo $orden, string $descripcion): TareaOrden
    {
        $this->exigirPreOrden($orden, 'tarea', 'Las tareas se definen en el requerimiento, antes de enviarlo.');

        return $orden->tareas()->create([
            'descripcion' => trim($descripcion),
            'posicion' => ((int) $orden->tareas()->max('posicion')) + 1,
        ]);
    }

    public function quitarTarea(TareaOrden $tarea): void
    {
        $this->exigirPreOrden($tarea->orden, 'tarea', 'El requerimiento ya se envió: las tareas no se quitan.');

        $tarea->delete();
    }

    /**
     * El mecánico pide un repuesto: del catálogo del almacén o en texto.
     * En el requerimiento va con él; con la orden ya aprobada es un
     * adicional que operaciones aprueba aparte.
     *
     * @param  array{articulo_id?: int|null, descripcion?: string|null, cantidad: float|int|string, unidad?: string|null}  $datos
     */
    public function pedirRepuesto(OrdenTrabajo $orden, User $mecanico, array $datos): RepuestoOrden
    {
        $enRequerimiento = $orden->estado === EstadoOrdenTrabajo::Solicitada;
        $enTrabajo = in_array($orden->estado, [EstadoOrdenTrabajo::Pendiente, EstadoOrdenTrabajo::EnProceso, EstadoOrdenTrabajo::Observada], true);

        if (! $enRequerimiento && ! $enTrabajo) {
            $this->rechazar('descripcion', "La {$orden->numero()} está ".mb_strtolower($orden->estado->label()).': no se le piden repuestos.');
        }

        $articulo = isset($datos['articulo_id']) ? Articulo::query()->find($datos['articulo_id']) : null;

        return $orden->repuestos()->create([
            'articulo_id' => $articulo?->id,
            'descripcion' => $articulo?->nombre ?? trim((string) ($datos['descripcion'] ?? '')),
            'cantidad' => $datos['cantidad'],
            'unidad' => $articulo?->unidad->value ?? ($datos['unidad'] ?? null),
            'adicional' => $enTrabajo,
            'user_id' => $mecanico->id,
        ]);
    }

    /**
     * Un repuesto pedido se quita mientras nadie lo haya aprobado.
     */
    public function quitarRepuesto(RepuestoOrden $repuesto): void
    {
        if ($repuesto->estaAprobado()) {
            $this->rechazar('repuesto', 'Ese repuesto ya está aprobado: no se quita.');
        }

        $repuesto->delete();
    }

    /**
     * Operaciones aprueba un repuesto adicional.
     */
    public function aprobarRepuesto(RepuestoOrden $repuesto, User $aprobador): void
    {
        if ($repuesto->orden->estado->sinAprobar()) {
            $this->rechazar('repuesto', 'Los repuestos del requerimiento se aprueban con él.');
        }

        $repuesto->update(['aprobado_at' => now(), 'aprobado_por' => $aprobador->id]);
    }

    /**
     * El mecánico envía su requerimiento a operaciones: tiene que decir qué
     * va a hacer y mostrar cómo llegó la unidad. Los repuestos son
     * opcionales: hay trabajos que no los necesitan.
     */
    public function enviarRequerimiento(OrdenTrabajo $orden, User $mecanico): void
    {
        $this->exigirPreOrden($orden, 'estado', "La {$orden->numero()} ya no es una pre-orden: el requerimiento ya se envió.");

        if ($orden->tareas()->doesntExist()) {
            $this->rechazar('tareas', 'Anota al menos una tarea: qué le vas a hacer a la unidad.');
        }

        if ($orden->getMedia('antes')->isEmpty()) {
            $this->rechazar('fotos', 'Toma al menos una foto de cómo llegó la unidad.');
        }

        $orden->update([
            'estado' => EstadoOrdenTrabajo::Requerimiento,
            'requerimiento_at' => now(),
            'requerimiento_por' => $mecanico->id,
            'devolucion_requerimiento' => null,
        ]);
    }

    /**
     * Operaciones aprueba el requerimiento: nace la orden de trabajo, con
     * sus repuestos aprobados, lista para que el mecánico la empiece.
     */
    public function aprobarRequerimiento(OrdenTrabajo $orden, User $aprobador): void
    {
        if ($orden->estado !== EstadoOrdenTrabajo::Requerimiento) {
            $this->rechazar('estado', "La {$orden->numero()} no tiene un requerimiento por aprobar.");
        }

        $orden->update([
            'estado' => EstadoOrdenTrabajo::Pendiente,
            'aprobado_at' => now(),
            'aprobado_por' => $aprobador->id,
        ]);

        $orden->repuestos()->whereNull('aprobado_at')->update(['aprobado_at' => now(), 'aprobado_por' => $aprobador->id]);
    }

    /**
     * Operaciones no aprueba el requerimiento: vuelve al mecánico como
     * pre-orden, con lo que hay que cambiar.
     */
    public function devolverRequerimiento(OrdenTrabajo $orden, User $aprobador, string $motivo): void
    {
        if ($orden->estado !== EstadoOrdenTrabajo::Requerimiento) {
            $this->rechazar('motivo', "La {$orden->numero()} no tiene un requerimiento por aprobar.");
        }

        $orden->update([
            'estado' => EstadoOrdenTrabajo::Solicitada,
            'devolucion_requerimiento' => $motivo,
        ]);
    }

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
     * El mecánico empieza: la orden tiene que estar aprobada y con al menos
     * una foto de cómo llegó la unidad (las del requerimiento valen).
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
     * marcar, diciendo por qué. La orden pasa a operaciones para validarla.
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
     * No se valida: vuelve al mecánico con lo que hay que corregir.
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
            'requerimientoPor:id,name', 'aprobadoPor:id,name', 'repuestos.articulo:id,stock', 'repuestos.pedidoPor:id,name',
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
            'repuestos' => $this->repuestos($orden),
            'devolucion_requerimiento' => $orden->estado === EstadoOrdenTrabajo::Solicitada ? $orden->devolucion_requerimiento : null,
            'pasos' => $this->pasos($orden),
            'llantas' => $this->llantas($orden),
            'nota_mecanico' => $orden->nota_mecanico,
            'observacion_revision' => $orden->observacion_revision,
        ];
    }

    /**
     * Lo que el mecánico pidió, frente a lo que el almacén ya le entregó a
     * la orden. Solo un repuesto del catálogo se puede cruzar con los vales;
     * lo entregado se reparte en orden entre las líneas del mismo artículo.
     *
     * @return list<array{id: int, descripcion: string, cantidad: float, unidad: string|null, del_catalogo: bool, stock: float|null, adicional: bool, aprobado: bool, entregado: float|null, pedido_por: string|null}>
     */
    private function repuestos(OrdenTrabajo $orden): array
    {
        $entregado = [];

        foreach ($orden->repuestos->isEmpty() || $orden->estado->sinAprobar() ? [] : $this->materiales->lineas($orden) as $linea) {
            $entregado[$linea['articulo_id']] = ($entregado[$linea['articulo_id']] ?? 0) + $linea['cantidad'] - $linea['devuelto'];
        }

        return $orden->repuestos
            ->map(function (RepuestoOrden $repuesto) use (&$entregado): array {
                $cantidad = (float) $repuesto->cantidad;
                $recibido = null;

                if ($repuesto->articulo_id !== null) {
                    $recibido = min($cantidad, $entregado[$repuesto->articulo_id] ?? 0);
                    $entregado[$repuesto->articulo_id] = ($entregado[$repuesto->articulo_id] ?? 0) - $recibido;
                }

                return [
                    'id' => $repuesto->id,
                    'descripcion' => $repuesto->descripcion,
                    'cantidad' => $cantidad,
                    'unidad' => $repuesto->unidad,
                    'del_catalogo' => $repuesto->articulo_id !== null,
                    'stock' => $repuesto->articulo === null ? null : (float) $repuesto->articulo->stock,
                    'adicional' => $repuesto->adicional,
                    'aprobado' => $repuesto->estaAprobado(),
                    'entregado' => $recibido,
                    'pedido_por' => $repuesto->pedidoPor?->name,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * La línea de tiempo: cada paso dado, con el usuario que lo dio.
     *
     * @return list<Paso>
     */
    private function pasos(OrdenTrabajo $orden): array
    {
        $preOrden = $orden->requerimiento_at !== null || $orden->estado === EstadoOrdenTrabajo::Solicitada;
        $revisada = $orden->revisado_at !== null;

        return array_values(array_filter([
            [
                'paso' => $preOrden ? 'Pre-orden creada' : 'Registrada',
                'usuario' => $orden->creador?->name,
                'cuando' => $orden->created_at?->toIso8601String(),
                'nota' => null,
            ],
            $orden->requerimiento_at === null ? null : [
                'paso' => 'Envió el requerimiento',
                'usuario' => $orden->requerimientoPor?->name,
                'cuando' => $orden->requerimiento_at->toIso8601String(),
                'nota' => null,
            ],
            $orden->aprobado_at === null ? null : [
                'paso' => 'Requerimiento aprobado: orden generada',
                'usuario' => $orden->aprobadoPor?->name,
                'cuando' => $orden->aprobado_at->toIso8601String(),
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
                'paso' => $orden->estado === EstadoOrdenTrabajo::Finalizada ? 'Validada y finalizada' : 'Observada',
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
        if ($orden->estado->sinAprobar()) {
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

    private function exigirPreOrden(OrdenTrabajo $orden, string $campo, string $mensaje): void
    {
        if ($orden->estado !== EstadoOrdenTrabajo::Solicitada) {
            $this->rechazar($campo, $mensaje);
        }
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
