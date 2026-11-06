<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarPuntoAbastecimientoRequest;
use App\Models\Abastecimiento;
use App\Models\CapacidadTanque;
use App\Models\LineaRuta;
use App\Models\PuntoAbastecimiento;
use App\Models\Vehiculo;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los puntos donde se carga combustible (grifos con convenio, Kio), con lo
 * que se cargó en el mes y el último precio. La dotación de cada ruta vive
 * en el catálogo de rutas.
 */
class ConfiguracionAbastecimientoController extends Controller
{
    public function index(): Response
    {
        $this->authorize('configurar', Abastecimiento::class);

        // Lo que se cargó en cada punto este mes y el último precio que se
        // pagó ahí: sirve para ver qué grifo se usa y negociar convenios.
        $mes = RelojOperativo::fechaDeHoy()->startOfMonth()->toDateString();
        $galonesDelMes = Abastecimiento::query()
            ->whereNotNull('punto_abastecimiento_id')
            ->whereDate('fecha', '>=', $mes)
            ->selectRaw('punto_abastecimiento_id, sum('.Abastecimiento::GALONES_QUE_CUENTAN.') as galones')
            ->groupBy('punto_abastecimiento_id')
            ->pluck('galones', 'punto_abastecimiento_id');
        $ultimosPrecios = Abastecimiento::query()
            ->whereNotNull('punto_abastecimiento_id')
            ->whereNotNull('precio_galon')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['punto_abastecimiento_id', 'precio_galon'])
            ->unique('punto_abastecimiento_id')
            ->pluck('precio_galon', 'punto_abastecimiento_id');

        return Inertia::render('abastecimiento/configuracion', [
            'puntos' => PuntoAbastecimiento::query()
                ->enOrden()
                ->get()
                ->map(fn (PuntoAbastecimiento $punto): array => [
                    'id' => $punto->id,
                    'nombre' => $punto->nombre,
                    'zona' => $punto->zona?->value,
                    'zona_label' => $punto->zona?->label(),
                    'detalle' => $punto->detalle,
                    'activo' => $punto->activo,
                    'galones_mes' => round((float) ($galonesDelMes[$punto->id] ?? 0), 2),
                    'ultimo_precio' => isset($ultimosPrecios[$punto->id]) ? (float) $ultimosPrecios[$punto->id] : null,
                ])
                ->all(),
            'zonas' => ZonaRuta::options(),
            'capacidades' => $this->capacidades(),
        ]);
    }

    /**
     * Cuántos galones caben en el tanque de cada marca: avisa de un voucher
     * que no cabe. Vacío, esa marca no se revisa.
     */
    public function guardarCapacidades(Request $request): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        $datos = $request->validate([
            'capacidades' => ['array'],
            'capacidades.*.marca' => ['required', 'string', 'max:60'],
            'capacidades.*.galones' => ['nullable', 'numeric', 'min:10', 'max:2000'],
        ]);

        foreach ($datos['capacidades'] ?? [] as $fila) {
            $marca = mb_strtoupper(trim($fila['marca']));

            if (blank($fila['galones'] ?? null)) {
                CapacidadTanque::query()->where('marca', $marca)->delete();

                continue;
            }

            CapacidadTanque::query()->updateOrCreate(['marca' => $marca], ['galones' => $fila['galones']]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Capacidades guardadas.']);
    }

    /**
     * Las marcas de los tractos activos, con su capacidad si se cargó.
     *
     * @return list<array{marca: string, unidades: int, galones: float|null}>
     */
    private function capacidades(): array
    {
        $cargadas = CapacidadTanque::query()->pluck('galones', 'marca');

        $marcas = Vehiculo::query()
            ->where('tipo', TipoVehiculo::Tracto)
            ->where('estado', EstadoVehiculo::Activo)
            ->whereNotNull('marca')
            ->selectRaw('upper(trim(marca)) as marca, count(*) as unidades')
            ->groupByRaw('upper(trim(marca))')
            ->orderBy('marca')
            ->get()
            ->map(fn (Vehiculo $fila): array => [
                'marca' => (string) $fila->marca,
                'unidades' => (int) $fila->getAttribute('unidades'),
                'galones' => isset($cargadas[$fila->marca]) ? (float) $cargadas[$fila->marca] : null,
            ]);

        return array_values($marcas->all());
    }

    public function storePunto(GuardarPuntoAbastecimientoRequest $request): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        PuntoAbastecimiento::query()->create([
            ...$request->validated(),
            'orden' => (int) PuntoAbastecimiento::query()->max('orden') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Punto agregado.']);
    }

    public function updatePunto(GuardarPuntoAbastecimientoRequest $request, PuntoAbastecimiento $punto): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        $punto->update($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => 'Punto actualizado.']);
    }

    /**
     * Un punto que ya se usó no se borra: se desactiva, para que los
     * registros viejos sigan diciendo dónde se cargó.
     */
    public function destroyPunto(PuntoAbastecimiento $punto): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        $usado = Abastecimiento::query()->where('punto_abastecimiento_id', $punto->id)->exists()
            || LineaRuta::query()->where('punto_abastecimiento_id', $punto->id)->exists();

        if ($usado) {
            $punto->update(['activo' => false]);

            return back()->with('toast', ['type' => 'success', 'message' => "{$punto->nombre} ya se usó: quedó desactivado."]);
        }

        $punto->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Punto quitado.']);
    }
}
