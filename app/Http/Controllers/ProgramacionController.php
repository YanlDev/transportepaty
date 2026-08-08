<?php

namespace App\Http\Controllers;

use App\Enums\EstadoProgramacion;
use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoVehiculo;
use App\Http\Requests\MarcarProgramacionRequest;
use App\Models\Programacion;
use App\Models\Vehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que dicen los avisos sueltos de WhatsApp sobre cada tracto: qué carga
 * tiene programada hoy, o si ya quedó libre. Una fila por tracto activo y
 * fecha consultada, igual que `AsistenciaController` — sin importador, sin
 * validador de rutas: se tipea lo que dice el aviso y punto.
 */
class ProgramacionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Programacion::class);

        $fecha = $this->fechaPedida($request);
        // Solo las unidades automáticas suben a mina: mismo filtro que ya
        // usan Asignaciones y (antes) Disponibilidad para separar la flota.
        $cajaPedida = $request->string('caja')->value();
        $caja = TipoCaja::tryFrom($cajaPedida);

        $tractos = Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->when($caja, fn (Builder $query, TipoCaja $caja) => $query->where('caja', $caja))
            ->with('asignacionVigenteComoTracto.conductor:id,nombres,apellidos')
            ->orderBy('placa')
            ->get();

        $programaciones = Programacion::query()
            ->whereIn('vehiculo_id', $tractos->pluck('id'))
            ->where('fecha', $fecha->toDateString())
            ->get()
            ->keyBy('vehiculo_id');

        $filas = $tractos->map(fn (Vehiculo $tracto): array => [
            'vehiculo_id' => $tracto->id,
            'placa' => $tracto->placa,
            'caja_label' => $tracto->caja?->label(),
            'conductor_nombre' => $tracto->asignacionVigenteComoTracto?->conductor->nombre_completo,
            'marca' => $this->comoMarca($programaciones->get($tracto->id)),
        ]);

        return Inertia::render('programacion/index', [
            'fecha' => $fecha->toDateString(),
            'filtros' => ['caja' => $cajaPedida],
            'cajas' => TipoCaja::options(),
            'clientes' => $this->clientesConocidos(),
            'filas' => $filas,
        ]);
    }

    public function marcar(MarcarProgramacionRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $this->authorize('create', Programacion::class);

        // firstOrNew hidrata la fila si ya existía: cambiar el estado del día
        // no debe perder una observación ya escrita, ni crear un duplicado.
        $programacion = Programacion::query()->firstOrNew([
            'vehiculo_id' => $vehiculo->id,
            'fecha' => $request->date('fecha')->toDateString(),
        ]);

        $nuevoEstado = $request->enum('estado', EstadoProgramacion::class);

        // Vistazo rápido del cambio: solo el inmediato anterior, no una
        // cadena completa. El primer marcado del día no cuenta como cambio.
        if ($programacion->exists && $programacion->estado !== $nuevoEstado) {
            $programacion->estado_anterior = $programacion->estado;
            $programacion->estado_cambiado_en = now();
        }

        $programacion->estado = $nuevoEstado;

        if ($request->has('destino')) {
            $programacion->destino = $request->string('destino')->value() ?: null;
        }

        if ($request->has('cliente')) {
            $programacion->cliente = $request->string('cliente')->value() ?: null;
        }

        if ($request->has('observaciones')) {
            $programacion->observaciones = $request->string('observaciones')->value() ?: null;
        }

        $programacion->save();

        return back();
    }

    /**
     * Vuelve a dejar el día sin marcar: un aviso mal tipeado no debe quedar
     * como dato permanente solo porque no se puede borrar.
     */
    public function destroy(Programacion $programacion): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $programacion->delete();

        return back();
    }

    /**
     * Minsur es el cliente de casi todo lo que se programa (manda el aviso
     * él mismo); el resto son clientes de carga particular que se van
     * sumando según lo que se escriba — sin catálogo administrable aparte,
     * la lista crece sola con lo que ya se tipeó antes.
     *
     * @return list<string>
     */
    private function clientesConocidos(): array
    {
        $vistos = Programacion::query()
            ->whereNotNull('cliente')
            ->distinct()
            ->orderBy('cliente')
            ->pluck('cliente')
            ->map(fn (mixed $cliente): string => (string) $cliente);

        return array_values(collect(['Minsur'])->concat($vistos)->unique()->all());
    }

    /**
     * La fecha pedida, o hoy si no se pidió una válida.
     */
    private function fechaPedida(Request $request): CarbonImmutable
    {
        $fecha = $request->string('fecha')->value();

        try {
            return $fecha === '' ? CarbonImmutable::today() : CarbonImmutable::parse($fecha);
        } catch (\Exception) {
            return CarbonImmutable::today();
        }
    }

    /**
     * @return array{programacion_id: int, estado: string, estado_label: string, estado_anterior_label: string|null, estado_cambiado_en: string|null, destino: string|null, cliente: string|null, observaciones: string|null}|null
     */
    private function comoMarca(?Programacion $programacion): ?array
    {
        if ($programacion === null) {
            return null;
        }

        return [
            'programacion_id' => $programacion->id,
            'estado' => $programacion->estado->value,
            'estado_label' => $programacion->estado->label(),
            'estado_anterior_label' => $programacion->estado_anterior?->label(),
            'estado_cambiado_en' => $programacion->estado_cambiado_en?->format('H:i'),
            'destino' => $programacion->destino,
            'cliente' => $programacion->cliente,
            'observaciones' => $programacion->observaciones,
        ];
    }
}
