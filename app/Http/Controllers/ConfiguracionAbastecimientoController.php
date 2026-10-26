<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarPuntoAbastecimientoRequest;
use App\Http\Requests\GuardarReglaAbastecimientoRequest;
use App\Models\Abastecimiento;
use App\Models\Ajuste;
use App\Models\LineaReglaAbastecimiento;
use App\Models\PuntoAbastecimiento;
use App\Models\ReglaAbastecimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La pizarra de abastecimiento hecha pantalla: los puntos donde se carga,
 * cuántos galones son un tanque lleno y las tarjetas de dotación. Cambiar
 * un monto es editar la tarjeta, no tocar código.
 */
class ConfiguracionAbastecimientoController extends Controller
{
    public function index(): Response
    {
        $this->authorize('configurar', Abastecimiento::class);

        return Inertia::render('abastecimiento/configuracion', [
            'galonesTanqueLleno' => Ajuste::valor(Ajuste::GALONES_TANQUE_LLENO),
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
                ])
                ->all(),
            'reglas' => ReglaAbastecimiento::query()
                ->enOrden()
                ->with('lineas.punto:id,nombre')
                ->get()
                ->map(fn (ReglaAbastecimiento $regla): array => [
                    'id' => $regla->id,
                    'nombre' => $regla->nombre,
                    'color' => $regla->color,
                    'sentido' => $regla->sentido?->value,
                    'zona_origen' => $regla->zona_origen?->value,
                    'zona_destino' => $regla->zona_destino?->value,
                    'tipo_carga' => $regla->tipo_carga?->value,
                    'lugares' => $regla->lugares,
                    'peso_desde' => $regla->peso_desde === null ? null : (float) $regla->peso_desde,
                    'peso_hasta' => $regla->peso_hasta === null ? null : (float) $regla->peso_hasta,
                    'nota' => $regla->nota,
                    'activa' => $regla->activa,
                    'se_aplica_sola' => $regla->especificidad() > 0,
                    'lineas' => $regla->lineas
                        ->map(fn (LineaReglaAbastecimiento $linea): array => [
                            'tipo' => $linea->tipo->value,
                            'concepto' => $linea->concepto,
                            'punto_abastecimiento_id' => $linea->punto_abastecimiento_id,
                            'punto' => $linea->punto?->nombre,
                            'galones' => $linea->galones === null ? null : (float) $linea->galones,
                            'tanque_lleno' => $linea->tanque_lleno,
                            'monto' => $linea->monto === null ? null : (float) $linea->monto,
                        ])
                        ->all(),
                ])
                ->all(),
            'opciones' => [
                'zonas' => ZonaRuta::options(),
                'sentidos' => [
                    ['value' => SentidoTramo::Ida->value, 'label' => 'Ida · bajada'],
                    ['value' => SentidoTramo::Regreso->value, 'label' => 'Regreso · subida'],
                ],
                'cargas' => TipoCarga::options(),
                'tiposLinea' => TipoLineaAbastecimiento::options(),
            ],
        ]);
    }

    public function actualizarTanque(Request $request): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        $request->validate(['galones' => ['nullable', 'numeric', 'min:1', 'max:999']]);

        Ajuste::guardar(Ajuste::GALONES_TANQUE_LLENO, $request->filled('galones') ? (string) $request->float('galones') : null);

        return back()->with('toast', ['type' => 'success', 'message' => 'Tanque lleno actualizado.']);
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
            || LineaReglaAbastecimiento::query()->where('punto_abastecimiento_id', $punto->id)->exists();

        if ($usado) {
            $punto->update(['activo' => false]);

            return back()->with('toast', ['type' => 'success', 'message' => "{$punto->nombre} ya se usó: quedó desactivado."]);
        }

        $punto->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Punto quitado.']);
    }

    public function storeRegla(GuardarReglaAbastecimientoRequest $request): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        DB::transaction(function () use ($request): void {
            $regla = ReglaAbastecimiento::query()->create([
                ...$this->datosDeRegla($request),
                'orden' => (int) ReglaAbastecimiento::query()->max('orden') + 1,
            ]);
            $this->guardarLineas($regla, $request);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Tarjeta agregada.']);
    }

    public function updateRegla(GuardarReglaAbastecimientoRequest $request, ReglaAbastecimiento $regla): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        DB::transaction(function () use ($request, $regla): void {
            $regla->update($this->datosDeRegla($request));
            $this->guardarLineas($regla, $request);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Tarjeta actualizada.']);
    }

    public function destroyRegla(ReglaAbastecimiento $regla): RedirectResponse
    {
        $this->authorize('configurar', Abastecimiento::class);

        $regla->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Tarjeta quitada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeRegla(GuardarReglaAbastecimientoRequest $request): array
    {
        return collect($request->validated())
            ->except('lineas')
            ->map(fn (mixed $valor): mixed => $valor === '' ? null : $valor)
            ->all();
    }

    private function guardarLineas(ReglaAbastecimiento $regla, GuardarReglaAbastecimientoRequest $request): void
    {
        $regla->lineas()->delete();

        /** @var list<array<string, mixed>> $lineas */
        $lineas = $request->validated('lineas', []);

        foreach ($lineas as $orden => $linea) {
            $esCombustible = $linea['tipo'] === TipoLineaAbastecimiento::Combustible->value;
            $lleno = $esCombustible && (bool) ($linea['tanque_lleno'] ?? false);

            $regla->lineas()->create([
                'tipo' => $linea['tipo'],
                'concepto' => $linea['concepto'],
                'punto_abastecimiento_id' => $esCombustible ? ($linea['punto_abastecimiento_id'] ?? null) : null,
                'galones' => $esCombustible && ! $lleno ? ($linea['galones'] ?? null) : null,
                'tanque_lleno' => $lleno,
                'monto' => $esCombustible ? null : ($linea['monto'] ?? null),
                'orden' => $orden + 1,
            ]);
        }
    }
}
