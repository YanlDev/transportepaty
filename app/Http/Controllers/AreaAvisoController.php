<?php

namespace App\Http\Controllers;

use App\Models\AreaAviso;
use App\Services\AvisoDeSalida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Las áreas que reciben los avisos de Programación, administradas desde el
 * panel de WhatsApp: agregar, cambiar el número, apagar o quitar un área.
 */
class AreaAvisoController extends Controller
{
    public function __construct(private readonly AvisoDeSalida $aviso) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        AreaAviso::query()->create([
            ...$this->datos($request),
            'orden' => (int) AreaAviso::query()->max('orden') + 1,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Área agregada.']);
    }

    public function update(Request $request, AreaAviso $area): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        $area->update($this->datos($request));

        return back()->with('toast', ['type' => 'success', 'message' => "{$area->nombre} actualizada."]);
    }

    public function destroy(AreaAviso $area): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        $area->delete();

        return back()->with('toast', ['type' => 'success', 'message' => "{$area->nombre} ya no recibe avisos."]);
    }

    /**
     * @return array{nombre: string, numero: string, ve_flete: bool, activa: bool, recibe_recordatorio: bool}
     */
    private function datos(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60'],
            'numero' => ['required', 'string', 'max:20', function (string $atributo, mixed $valor, \Closure $fallar): void {
                if ($this->aviso->numeroWhatsapp((string) $valor) === null) {
                    $fallar('Escribe un celular válido, ej. 950301881.');
                }
            }],
            've_flete' => ['boolean'],
            'activa' => ['boolean'],
            'recibe_recordatorio' => ['boolean'],
        ]);

        return [
            'nombre' => trim($datos['nombre']),
            'numero' => preg_replace('/\D/', '', $datos['numero']) ?? '',
            've_flete' => (bool) ($datos['ve_flete'] ?? false),
            'activa' => (bool) ($datos['activa'] ?? true),
            'recibe_recordatorio' => (bool) ($datos['recibe_recordatorio'] ?? false),
        ];
    }
}
