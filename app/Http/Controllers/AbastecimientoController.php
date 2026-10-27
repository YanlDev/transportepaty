<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Http\Requests\RegistrarAbastecimientoRequest;
use App\Models\Abastecimiento;
use App\Models\Ajuste;
use App\Models\ParadaVuelta;
use App\Models\PuntoAbastecimiento;
use App\Models\Vehiculo;
use App\Models\Vuelta;
use App\Services\DotacionVuelta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero de abastecimiento: cada unidad con su vuelta abierta, lo que le
 * toca según la pizarra, lo que ya se le autorizó y lo que le falta; y el
 * registro de lo que se autoriza por el QR de Repsol (o en Kio).
 */
class AbastecimientoController extends Controller
{
    /** Cuántos registros recientes se muestran debajo del tablero. */
    private const RECIENTES = 40;

    public function __construct(private readonly DotacionVuelta $dotacion) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Abastecimiento::class);

        $vueltas = Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->with([
                'tracto:id,placa',
                'conductor:id,nombres,apellidos',
                'paradas.viajes:id,parada_vuelta_id,numero_gr,origen,destino,peso,unidad_peso',
            ])
            ->get()
            // La que ya volvió a la base no espera más combustible: lo que
            // cargue para salir es del ticket siguiente.
            ->reject(fn (Vuelta $vuelta): bool => $vuelta->etapa() === 'en_base')
            ->sortBy(fn (Vuelta $vuelta): string => $vuelta->tracto->placa.'|'.$vuelta->inicio?->toDateString())
            ->values();

        $registrados = Abastecimiento::query()
            ->whereIn('vuelta_id', $vueltas->pluck('id'))
            ->with('punto:id,nombre')
            ->get();

        return Inertia::render('abastecimiento/index', [
            'vueltas' => $vueltas
                ->map(fn (Vuelta $vuelta): array => [
                    'id' => $vuelta->id,
                    'codigo' => $vuelta->codigo,
                    'estado' => $vuelta->estado->value,
                    'estado_label' => $vuelta->estado->label(),
                    'inicio' => $vuelta->inicio?->toDateString(),
                    'tracto' => ['id' => $vuelta->tracto->id, 'placa' => $vuelta->tracto->placa],
                    'conductor' => $vuelta->conductor ? "{$vuelta->conductor->nombres} {$vuelta->conductor->apellidos}" : null,
                    'etapa' => $vuelta->etapa(),
                    'recorrido' => $this->recorrido($vuelta),
                    ...$this->dotacion->de($vuelta, $registrados->where('vuelta_id', $vuelta->id)->values()),
                    'importe_registrado' => round((float) $registrados->where('vuelta_id', $vuelta->id)->sum('importe'), 2),
                ])
                ->all(),
            'recientes' => Abastecimiento::query()
                ->with(['vehiculo:id,placa', 'punto:id,nombre', 'vuelta:id,codigo', 'registradoPor:id,name'])
                ->latest('fecha')
                ->latest('id')
                ->limit(self::RECIENTES)
                ->get()
                ->map(fn (Abastecimiento $abastecimiento): array => [
                    'id' => $abastecimiento->id,
                    'fecha' => $abastecimiento->fecha->toDateString(),
                    'placa' => $abastecimiento->vehiculo->placa,
                    'punto' => $abastecimiento->punto?->nombre,
                    'vuelta' => $abastecimiento->vuelta?->codigo,
                    'galones' => (float) $abastecimiento->galones,
                    'tanque_lleno' => $abastecimiento->tanque_lleno,
                    'precio_galon' => $abastecimiento->precio_galon === null ? null : (float) $abastecimiento->precio_galon,
                    'importe' => $abastecimiento->importe === null ? null : (float) $abastecimiento->importe,
                    'observacion' => $abastecimiento->observacion,
                    'registrado_por' => $abastecimiento->registradoPor?->name,
                ])
                ->all(),
            'tractos' => fn (): array => $this->opcionesTractos(),
            'puntos' => fn (): array => $this->opcionesPuntos(),
            'galonesTanqueLleno' => fn (): ?float => ($valor = Ajuste::valor(Ajuste::GALONES_TANQUE_LLENO)) === null ? null : (float) $valor,
        ]);
    }

    public function store(RegistrarAbastecimientoRequest $request): RedirectResponse
    {
        $this->authorize('create', Abastecimiento::class);

        $fecha = Carbon::parse($request->string('fecha')->toString());
        $vuelta = $request->filled('vuelta_id')
            ? Vuelta::query()->find($request->integer('vuelta_id'))
            : $this->vueltaAbierta($request->integer('vehiculo_id'), $fecha);
        $lleno = $request->boolean('tanque_lleno');
        $galones = $request->filled('galones')
            ? $request->float('galones')
            : (float) Ajuste::valor(Ajuste::GALONES_TANQUE_LLENO);
        $precio = $request->filled('precio_galon') ? $request->float('precio_galon') : null;

        Abastecimiento::query()->create([
            'vehiculo_id' => $request->integer('vehiculo_id'),
            'vuelta_id' => $vuelta?->id,
            'punto_abastecimiento_id' => $request->filled('punto_abastecimiento_id') ? $request->integer('punto_abastecimiento_id') : null,
            'conductor_id' => $vuelta?->conductor_id,
            'fecha' => $fecha,
            'galones' => $galones,
            'tanque_lleno' => $lleno,
            'precio_galon' => $precio,
            'importe' => $precio === null ? null : round($galones * $precio, 2),
            'observacion' => $request->string('observacion')->toString() ?: null,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Abastecimiento registrado'.($vuelta ? " en la vuelta {$vuelta->codigo}." : '.'),
        ]);
    }

    public function destroy(Abastecimiento $abastecimiento): RedirectResponse
    {
        $this->authorize('delete', $abastecimiento);

        $abastecimiento->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Registro quitado.']);
    }

    /**
     * La vuelta en la que anda la unidad ese día: la abierta más reciente que
     * ya arrancó (o arranca en un par de días: se abastece antes de salir).
     */
    private function vueltaAbierta(int $tractoId, Carbon $fecha): ?Vuelta
    {
        return Vuelta::query()
            ->abiertasDeLaUnidad($tractoId, $fecha->copy()->addDays(2))
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Las paradas de la vuelta en una línea, para reconocerla de un vistazo.
     *
     * @return list<array{sentido: string, texto: string, carga: string}>
     */
    private function recorrido(Vuelta $vuelta): array
    {
        return array_values($vuelta->paradas
            ->map(fn (ParadaVuelta $parada): array => [
                'sentido' => $parada->sentido === SentidoTramo::Ida ? 'ida' : 'regreso',
                'texto' => ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?'),
                'carga' => $parada->tipo_carga->label(),
            ])
            ->all());
    }

    /**
     * Los puntos activos con el último precio por galón que se pagó en cada
     * uno: elegir el punto deja puesto ese precio (si cambió, se corrige).
     *
     * @return list<array{id: int, nombre: string, ultimo_precio: float|null}>
     */
    private function opcionesPuntos(): array
    {
        $puntos = PuntoAbastecimiento::query()
            ->where('activo', true)
            ->enOrden()
            ->get(['id', 'nombre']);

        $precios = Abastecimiento::query()
            ->whereIn('punto_abastecimiento_id', $puntos->pluck('id'))
            ->whereNotNull('precio_galon')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['punto_abastecimiento_id', 'precio_galon'])
            ->unique('punto_abastecimiento_id')
            ->keyBy('punto_abastecimiento_id');

        return array_values($puntos
            ->map(fn (PuntoAbastecimiento $punto): array => [
                'id' => $punto->id,
                'nombre' => $punto->nombre,
                'ultimo_precio' => ($precio = $precios->get($punto->id)?->precio_galon) === null ? null : (float) $precio,
            ])
            ->all());
    }

    /**
     * Los tractos activos, con su vuelta abierta si tienen: elegir la unidad
     * deja puesta la vuelta.
     *
     * @return array<int, array{id: int, placa: string, vuelta_id: int|null, vuelta: string|null}>
     */
    private function opcionesTractos(): array
    {
        $abiertas = Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->orderBy('inicio')
            ->get(['id', 'codigo', 'tracto_id'])
            ->keyBy('tracto_id');

        return Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->orderBy('placa')
            ->get(['id', 'placa'])
            ->map(fn (Vehiculo $tracto): array => [
                'id' => $tracto->id,
                'placa' => $tracto->placa,
                'vuelta_id' => $abiertas->get($tracto->id)?->id,
                'vuelta' => $abiertas->get($tracto->id)?->codigo,
            ])
            ->all();
    }
}
