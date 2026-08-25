<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * El nombre de razón social de Minsur trae variantes de espaciado en las
     * GR reales («MINSUR S.A.» y «MINSUR S. A.»), así que se identifica por
     * prefijo en vez de comparar el texto exacto.
     */
    private const CLIENTE_MINSUR_PREFIJO = 'MINSUR';

    public function index(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'resumen' => [
                'tractos' => Vehiculo::where('tipo', TipoVehiculo::Tracto)->count(),
                'carretas' => Vehiculo::where('tipo', TipoVehiculo::Carreta)->count(),
                'operativos' => Vehiculo::where('estado', EstadoVehiculo::Activo)->count(),
                'conductores' => Conductor::where('activo', true)->count(),
                'novedadesActivas' => Novedad::vigentes()->count(),
                'documentosVencidos' => $this->documentosVencidos(),
            ],
            'filtroMes' => $this->filtroMes($request),
            'mesesDisponibles' => $this->mesesDisponibles(),
            'cargaMinsur' => $this->cargaMinsur($request),
            'viajesPorCliente' => $this->viajesPorClienteOtros(),
        ]);
    }

    /**
     * Total de papeles ya vencidos, de fierros y de conductores: el número de
     * la tarjeta de alerta. El detalle vive en Vehículos/Conductores, no acá.
     */
    private function documentosVencidos(): int
    {
        $hoy = now()->toDateString();

        $deVehiculos = VehiculoDocumento::query()
            ->whereHas('vehiculo')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->count();

        $deConductores = Conductor::query()
            ->whereHas('documentos', function (Builder $query) use ($hoy): void {
                $query->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', $hoy);
            })
            ->count();

        return $deVehiculos + $deConductores;
    }

    /**
     * Todos los viajes de Minsur, cargados una sola vez por request —
     * `filtroMes()`, `mesesDisponibles()` y `cargaMinsur()` la comparten en
     * vez de repetir la consulta. El formato de mes se calcula en PHP con
     * Carbon en vez de una función de fecha en SQL (`to_char`, `strftime`...)
     * porque esas no son portables entre Postgres (producción) y SQLite
     * (tests).
     *
     * @return Collection<int, Viaje>
     */
    private function viajesMinsur(): Collection
    {
        return once(fn (): Collection => Viaje::query()
            ->where('cliente', 'like', self::CLIENTE_MINSUR_PREFIJO.'%')
            ->get(['fecha_traslado', 'tracto_id', 'placa_tracto', 'carreta_id', 'placa_carreta', 'conductor_id', 'conductor_dni', 'tipo_carga']));
    }

    /**
     * El mes elegido en el filtro (`YYYY-MM`), o el mes más reciente con
     * viajes de Minsur si no se pidió ninguno o el pedido no es válido —así
     * el gráfico nunca abre vacío por default.
     */
    private function filtroMes(Request $request): ?string
    {
        $mes = $request->string('mes')->value();

        if ($mes !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1) {
            return $mes;
        }

        return $this->mesesDisponibles()[0] ?? null;
    }

    /**
     * Meses (`YYYY-MM`) que tienen al menos un viaje de Minsur, del más
     * reciente al más antiguo — opciones del selector del filtro.
     *
     * @return list<string>
     */
    private function mesesDisponibles(): array
    {
        return $this->viajesMinsur()
            ->map(fn (Viaje $viaje): string => $viaje->fecha_traslado->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Qué llevaron las unidades de Minsur en el mes elegido, contado por
     * viaje real —no por GR—: un mismo camión puede salir una vez con dos
     * GR, incluso cruzando a un segundo día (ver `Viaje::contarViajesReales()`),
     * y ahí solo debe contar una carga. Se agrupa por tipo de carga primero
     * para que el conteo tolerante no funda viajes de tipos distintos entre
     * sí. En el orden fijo del enum, incluidos los tipos en cero, para que la
     * mezcla se lea completa de un vistazo.
     *
     * @return list<array{tipo: string, label: string, valor: int}>
     */
    private function cargaMinsur(Request $request): array
    {
        $mes = $this->filtroMes($request);

        $porTipo = $this->viajesMinsur()
            ->when($mes !== null, fn (Collection $viajes) => $viajes->filter(
                fn (Viaje $viaje): bool => $viaje->fecha_traslado->format('Y-m') === $mes,
            ))
            ->groupBy(fn (Viaje $viaje): string => $viaje->tipo_carga->value);

        $excluidos = TipoCarga::excluidosDeViaje();

        $tipos = array_values(array_filter(
            TipoCarga::cases(),
            fn (TipoCarga $tipo): bool => ! in_array($tipo, $excluidos, true),
        ));

        return array_map(
            fn (TipoCarga $tipo): array => [
                'tipo' => $tipo->value,
                'label' => $tipo->label(),
                'valor' => Viaje::contarViajesReales($porTipo->get($tipo->value, collect())),
            ],
            $tipos,
        );
    }

    /**
     * Cuántos viajes reales —no GR— tiene cada cliente que no sea Minsur.
     * Mismo criterio de agrupación que `cargaMinsur()`.
     *
     * @return list<array{cliente: string, valor: int}>
     */
    private function viajesPorClienteOtros(): array
    {
        return Viaje::query()
            ->where('cliente', 'not like', self::CLIENTE_MINSUR_PREFIJO.'%')
            ->get(['cliente', 'fecha_traslado', 'tracto_id', 'placa_tracto', 'carreta_id', 'placa_carreta', 'conductor_id', 'conductor_dni'])
            ->groupBy('cliente')
            ->map(fn (Collection $viajes, string $cliente): array => [
                'cliente' => $cliente,
                'valor' => Viaje::contarViajesReales($viajes),
            ])
            ->sortByDesc('valor')
            ->values()
            ->all();
    }
}
