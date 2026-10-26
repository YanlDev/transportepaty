<?php

namespace App\Http\Controllers;

use App\Enums\Permiso;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Models\DestinoParticular;
use App\Models\OpcionTramo;
use App\Models\PalabraZona;
use App\Services\PalabrasDeZona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que antes estaba fijo en el código de la programación: las opciones
 * para programar la ida y la vuelta, los destinos de la carga particular y
 * las palabras con que se reconoce cada zona en las GR.
 */
class ConfiguracionProgramacionController extends Controller
{
    public function __construct(private readonly PalabrasDeZona $palabras) {}

    public function index(Request $request): Response
    {
        $this->autorizar($request);

        return Inertia::render('vueltas/configuracion', [
            'opciones' => OpcionTramo::query()->enOrden()->get()
                ->map(fn (OpcionTramo $opcion): array => [
                    'id' => $opcion->id,
                    'nombre' => $opcion->nombre,
                    'descripcion' => $opcion->descripcion,
                    'sentido' => $opcion->sentido->value,
                    'paradas' => $opcion->paradas,
                    'pide_destino' => $opcion->pide_destino,
                    'activa' => $opcion->activa,
                ])
                ->all(),
            'destinos' => DestinoParticular::query()->enOrden()->get()
                ->map(fn (DestinoParticular $destino): array => [
                    'id' => $destino->id,
                    'lugar' => $destino->lugar,
                    'zona' => $destino->zona->value,
                    'activo' => $destino->activo,
                ])
                ->all(),
            'palabras' => PalabraZona::query()->orderBy('palabra')->get()
                ->map(fn (PalabraZona $palabra): array => [
                    'id' => $palabra->id,
                    'zona' => $palabra->zona->value,
                    'palabra' => $palabra->palabra,
                ])
                ->all(),
            'zonas' => array_map(fn (ZonaRuta $zona): array => [
                'value' => $zona->value,
                'label' => $zona->label(),
                'base' => $zona->esBase(),
            ], ZonaRuta::cases()),
            'cargas' => TipoCarga::options(),
        ]);
    }

    public function storeOpcion(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        OpcionTramo::query()->create([
            ...$this->datosDeOpcion($request),
            'orden' => (int) OpcionTramo::query()->max('orden') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Opción agregada.']);
    }

    public function updateOpcion(Request $request, OpcionTramo $opcion): RedirectResponse
    {
        $this->autorizar($request);

        $opcion->update($this->datosDeOpcion($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Opción actualizada.']);
    }

    public function destroyOpcion(Request $request, OpcionTramo $opcion): RedirectResponse
    {
        $this->autorizar($request);

        $opcion->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Opción quitada.']);
    }

    public function storeDestino(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        DestinoParticular::query()->create([
            ...$this->datosDeDestino($request),
            'orden' => (int) DestinoParticular::query()->max('orden') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Destino agregado.']);
    }

    public function updateDestino(Request $request, DestinoParticular $destino): RedirectResponse
    {
        $this->autorizar($request);

        $destino->update($this->datosDeDestino($request, $destino));

        return back()->with('toast', ['type' => 'success', 'message' => 'Destino actualizado.']);
    }

    public function destroyDestino(Request $request, DestinoParticular $destino): RedirectResponse
    {
        $this->autorizar($request);

        $destino->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Destino quitado.']);
    }

    /**
     * Las palabras se guardan en mayúsculas: las direcciones de las GR se
     * comparan así.
     */
    public function storePalabra(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        $request->merge(['palabra' => mb_strtoupper(trim($request->string('palabra')->toString()))]);
        $datos = $request->validate([
            'zona' => ['required', Rule::enum(ZonaRuta::class)],
            'palabra' => ['required', 'string', 'min:2', 'max:60', Rule::unique('palabras_zona', 'palabra')],
        ], ['palabra.unique' => 'Esa palabra ya reconoce otra zona.']);

        PalabraZona::query()->create($datos);
        $this->palabras->olvidar();

        return back()->with('toast', ['type' => 'success', 'message' => "«{$datos['palabra']}» agregada."]);
    }

    public function destroyPalabra(Request $request, PalabraZona $palabra): RedirectResponse
    {
        $this->autorizar($request);

        $palabra->delete();
        $this->palabras->olvidar();

        return back()->with('toast', ['type' => 'success', 'message' => "«{$palabra->palabra}» quitada."]);
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()?->checkPermissionTo(Permiso::ProgramacionConfigurar), 403);
    }

    /**
     * Una opción pide destino si alguna parada lo deja vacío (la carga
     * particular): ese destino se elige al programar.
     *
     * @return array<string, mixed>
     */
    private function datosDeOpcion(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:120'],
            'sentido' => ['required', Rule::enum(SentidoTramo::class)],
            'activa' => ['boolean'],
            'paradas' => ['required', 'array', 'min:1', 'max:6'],
            'paradas.*.zona_origen' => ['required', Rule::enum(ZonaRuta::class)],
            'paradas.*.zona_destino' => ['nullable', Rule::enum(ZonaRuta::class)],
            'paradas.*.tipo_carga' => ['required', Rule::enum(TipoCarga::class)],
        ], [], ['paradas.*.zona_origen' => 'desde', 'paradas.*.tipo_carga' => 'carga']);

        $paradas = array_map(fn (array $parada): array => [
            'zona_origen' => $parada['zona_origen'],
            'zona_destino' => $parada['zona_destino'] ?? null,
            'tipo_carga' => $parada['tipo_carga'],
        ], array_values($datos['paradas']));

        return [
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'sentido' => $datos['sentido'],
            'activa' => $datos['activa'] ?? true,
            'paradas' => $paradas,
            'pide_destino' => in_array(null, array_column($paradas, 'zona_destino'), true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeDestino(Request $request, ?DestinoParticular $destino = null): array
    {
        return $request->validate([
            'lugar' => ['required', 'string', 'max:60', Rule::unique('destinos_particulares', 'lugar')->ignore($destino)],
            'zona' => ['required', Rule::enum(ZonaRuta::class)],
            'activo' => ['boolean'],
        ]);
    }
}
