<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\ZonaRuta;
use App\Http\Requests\GuardarRutaRequest;
use App\Models\LineaRuta;
use App\Models\PuntoAbastecimiento;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\VarianteRuta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El catálogo de rutas: cada tramo estándar con su recorrido y su dotación.
 * Es lo único que se elige al programar y lo único que abastecimiento
 * ejecuta. Editar una ruta no toca los tickets que ya la tienen (llevan su
 * copia).
 */
class RutaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('configurar', Ruta::class);

        $usadas = RutaVuelta::query()->whereNotNull('ruta_id')->distinct()->pluck('ruta_id')->all();

        return Inertia::render('rutas/index', [
            'rutas' => Ruta::query()
                ->enOrden()
                ->with(['lineas.punto:id,nombre', 'variantes'])
                ->get()
                ->map(fn (Ruta $ruta): array => [
                    ...$this->datos($ruta),
                    'usada' => in_array($ruta->id, $usadas, true),
                ])
                ->all(),
            'opciones' => [
                'zonas' => ZonaRuta::options(),
                'cargas' => TipoCarga::options(),
                'tiposLinea' => TipoLineaAbastecimiento::options(),
                'puntos' => PuntoAbastecimiento::query()->where('activo', true)->enOrden()->get(['id', 'nombre'])
                    ->map(fn (PuntoAbastecimiento $punto): array => ['value' => (string) $punto->id, 'label' => $punto->nombre])
                    ->all(),
            ],
        ]);
    }

    public function store(GuardarRutaRequest $request): RedirectResponse
    {
        $this->authorize('configurar', Ruta::class);

        $ruta = DB::transaction(function () use ($request): Ruta {
            $ruta = Ruta::query()->create([...$this->campos($request), 'orden' => (int) Ruta::query()->max('orden') + 1]);
            $this->guardarDotacion($ruta, $request);

            return $ruta;
        });

        return back()->with('toast', ['type' => 'success', 'message' => "Ruta «{$ruta->nombre}» creada."]);
    }

    public function update(GuardarRutaRequest $request, Ruta $ruta): RedirectResponse
    {
        $this->authorize('configurar', Ruta::class);

        DB::transaction(function () use ($request, $ruta): void {
            $ruta->update($this->campos($request));
            $this->guardarDotacion($ruta, $request);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "Ruta «{$ruta->nombre}» actualizada. Los tickets que ya la tienen no cambian."]);
    }

    /**
     * Una ruta que ya está en algún ticket no se borra: se desactiva, para
     * que esos tickets sigan diciendo de dónde salió su dotación.
     */
    public function destroy(Ruta $ruta): RedirectResponse
    {
        $this->authorize('configurar', Ruta::class);

        if (RutaVuelta::query()->where('ruta_id', $ruta->id)->exists()) {
            $ruta->update(['activa' => false]);

            return back()->with('toast', ['type' => 'success', 'message' => "«{$ruta->nombre}» ya está en tickets: quedó desactivada."]);
        }

        $ruta->delete();

        return back()->with('toast', ['type' => 'success', 'message' => "Ruta «{$ruta->nombre}» quitada."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function campos(GuardarRutaRequest $request): array
    {
        $paradas = array_map(fn (array $parada): array => array_filter([
            'zona_origen' => $parada['zona_origen'],
            'zona_destino' => $parada['zona_destino'] ?? null,
            'tipo_carga' => $parada['tipo_carga'],
            'lugar_origen' => filled($parada['lugar_origen'] ?? null) ? trim($parada['lugar_origen']) : null,
            'lugar_destino' => filled($parada['lugar_destino'] ?? null) ? trim($parada['lugar_destino']) : null,
        ], fn ($valor, $clave) => $valor !== null || $clave === 'zona_destino', ARRAY_FILTER_USE_BOTH), array_values((array) $request->validated('paradas', [])));

        return [
            'nombre' => $request->string('nombre')->squish()->toString(),
            'descripcion' => $request->filled('descripcion') ? $request->string('descripcion')->squish()->toString() : null,
            'sentido' => $request->filled('sentido') ? $request->string('sentido')->toString() : null,
            'es_complemento' => $request->boolean('es_complemento'),
            'paradas' => $paradas,
            // La carga particular a un destino que se elige al programar.
            'pide_destino' => in_array(null, array_column($paradas, 'zona_destino'), true),
            'dias_viaje' => $request->filled('dias_viaje') ? $request->integer('dias_viaje') : null,
            'km_estandar' => $request->filled('km_estandar') ? $request->integer('km_estandar') : null,
            'color' => $request->filled('color') ? $request->string('color')->toString() : null,
            'nota' => $request->filled('nota') ? $request->string('nota')->toString() : null,
            'activa' => $request->boolean('activa', true),
        ];
    }

    /**
     * Guarda variantes y líneas conservando los ids de las que siguen: los
     * viáticos depositados y las copias de los tickets las nombran.
     */
    private function guardarDotacion(Ruta $ruta, GuardarRutaRequest $request): void
    {
        $idsVariantes = [];

        foreach (array_values((array) $request->validated('variantes', [])) as $orden => $datos) {
            $variante = $ruta->variantes()->whereKey($datos['id'] ?? 0)->first() ?? new VarianteRuta(['ruta_id' => $ruta->id]);
            $variante->fill([
                'nombre' => trim($datos['nombre']),
                'peso_desde' => $datos['peso_desde'] ?? null,
                'peso_hasta' => $datos['peso_hasta'] ?? null,
                'orden' => $orden + 1,
            ])->save();
            $idsVariantes[$orden] = $variante->id;
        }

        $ruta->variantes()->whereNotIn('id', $idsVariantes)->get()->each->delete();

        $idsLineas = [];
        $combustible = TipoLineaAbastecimiento::Combustible->value;

        foreach (array_values((array) $request->validated('lineas', [])) as $orden => $datos) {
            $esCombustible = $datos['tipo'] === $combustible;
            $linea = $ruta->lineas()->whereKey($datos['id'] ?? 0)->first() ?? new LineaRuta(['ruta_id' => $ruta->id]);
            $linea->fill([
                'variante_ruta_id' => isset($datos['variante']) ? ($idsVariantes[(int) $datos['variante']] ?? null) : null,
                'tipo' => $datos['tipo'],
                'concepto' => trim($datos['concepto']),
                'punto_abastecimiento_id' => $esCombustible ? ($datos['punto_abastecimiento_id'] ?? null) : null,
                'galones' => $esCombustible && ! ($datos['tanque_lleno'] ?? false) ? ($datos['galones'] ?? null) : null,
                'tanque_lleno' => $esCombustible && (bool) ($datos['tanque_lleno'] ?? false),
                'monto' => $esCombustible ? null : ($datos['monto'] ?? null),
                'orden' => $orden + 1,
            ])->save();
            $idsLineas[] = $linea->id;
        }

        $ruta->lineas()->whereNotIn('id', $idsLineas)->get()->each->delete();
    }

    /**
     * La ruta como la edita el formulario: cada línea dice su variante por
     * posición.
     *
     * @return array<string, mixed>
     */
    private function datos(Ruta $ruta): array
    {
        $posicion = $ruta->variantes->pluck('id')->flip();

        return [
            'id' => $ruta->id,
            'nombre' => $ruta->nombre,
            'descripcion' => $ruta->descripcion,
            'sentido' => $ruta->sentido?->value,
            'es_complemento' => $ruta->es_complemento,
            'paradas' => $ruta->paradas,
            'pide_destino' => $ruta->pide_destino,
            'dias_viaje' => $ruta->dias_viaje,
            'km_estandar' => $ruta->km_estandar,
            'color' => $ruta->color,
            'nota' => $ruta->nota,
            'activa' => $ruta->activa,
            'variantes' => $ruta->variantes->map(fn (VarianteRuta $variante): array => [
                'id' => $variante->id,
                'nombre' => $variante->nombre,
                'peso_desde' => $variante->peso_desde === null ? null : (float) $variante->peso_desde,
                'peso_hasta' => $variante->peso_hasta === null ? null : (float) $variante->peso_hasta,
            ])->all(),
            'lineas' => $ruta->lineas->map(fn (LineaRuta $linea): array => [
                'id' => $linea->id,
                'variante' => $linea->variante_ruta_id === null ? null : $posicion->get($linea->variante_ruta_id),
                'tipo' => $linea->tipo->value,
                'concepto' => $linea->concepto,
                'punto_abastecimiento_id' => $linea->punto_abastecimiento_id,
                'punto' => $linea->punto?->nombre,
                'galones' => $linea->galones === null ? null : (float) $linea->galones,
                'tanque_lleno' => $linea->tanque_lleno,
                'monto' => $linea->monto === null ? null : (float) $linea->monto,
            ])->all(),
            'tope' => $ruta->tieneVariantes() ? null : $ruta->topePara(null),
            'sentidos' => array_values(array_map(fn (SentidoTramo $sentido): string => $sentido->value, array_filter(SentidoTramo::cases(), fn (SentidoTramo $sentido): bool => $ruta->sirvePara($sentido)))),
        ];
    }
}
