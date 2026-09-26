<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\AreaAviso;
use App\Services\AvisoDeSalida;
use App\Services\RelojOperativo;
use App\Services\WhatsappServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * La pantalla del número de WhatsApp de la empresa: vincularlo, ver si sigue
 * conectado y mandar un mensaje de prueba. Los avisos de verdad salen desde
 * cada módulo; acá solo se administra la conexión.
 */
class WhatsappController extends Controller
{
    public function __construct(
        private readonly WhatsappServicio $whatsapp,
        private readonly AvisoDeSalida $aviso,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('administrar-whatsapp');

        return Inertia::render('whatsapp/index', [
            // En closure: la página lo refresca sola mientras se vincula.
            'estado' => fn (): array => $this->whatsapp->estado(),
            'codigoVinculacion' => fn (): ?string => $request->session()->get('codigo_vinculacion'),
            'areas' => fn () => AreaAviso::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get(['id', 'nombre', 'numero', 've_flete', 'activa', 'recibe_recordatorio']),
            'telefonoOficina' => fn (): ?string => $this->aviso->telefonoOficina(),
            'horaRecordatorio' => fn (): ?string => Ajuste::valor(Ajuste::HORA_RECORDATORIO),
        ]);
    }

    public function vincular(Request $request): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        $datos = $request->validate([
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $telefono = $this->aviso->numeroWhatsapp($datos['telefono'] ?? null);

        try {
            $codigo = $this->whatsapp->vincular($telefono);
        } catch (RuntimeException $error) {
            return back()->with('toast', ['type' => 'error', 'message' => $error->getMessage()]);
        }

        return back()->with('codigo_vinculacion', $codigo);
    }

    public function probar(Request $request): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        $datos = $request->validate([
            'numero' => ['required', 'string', 'max:20'],
        ]);

        $numero = $this->aviso->numeroWhatsapp($datos['numero']);

        if ($numero === null) {
            return back()->withErrors(['numero' => 'Escribe un número de celular válido.']);
        }

        try {
            $this->whatsapp->enviar($numero, sprintf(
                "✅ Mensaje de prueba de Transpaty\n%s",
                RelojOperativo::ahora()->format('d/m/Y H:i'),
            ));
        } catch (RuntimeException $error) {
            return back()->with('toast', ['type' => 'error', 'message' => $error->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => "Mensaje de prueba enviado a {$numero}."]);
    }

    /** El teléfono de la oficina, que va al pie de la advertencia. */
    public function actualizarOficina(Request $request): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        $datos = $request->validate([
            'telefono_oficina' => ['nullable', 'string', 'max:80'],
        ]);

        Ajuste::guardar(Ajuste::TELEFONO_OFICINA, $datos['telefono_oficina'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => 'Teléfono de la oficina actualizado.']);
    }

    /**
     * A qué hora se avisa de las unidades sin GR. Vacío apaga el
     * recordatorio sin tener que desmarcar las áreas.
     */
    public function actualizarRecordatorio(Request $request): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        $datos = $request->validate([
            'hora' => ['nullable', 'date_format:H:i'],
        ]);

        Ajuste::guardar(Ajuste::HORA_RECORDATORIO, $datos['hora'] ?? null);

        return back()->with('toast', [
            'type' => 'success',
            'message' => filled($datos['hora'] ?? null) ? "Recordatorio sin GR a las {$datos['hora']}." : 'Recordatorio sin GR apagado.',
        ]);
    }

    public function desvincular(): RedirectResponse
    {
        $this->authorize('administrar-whatsapp');

        try {
            $this->whatsapp->desvincular();
        } catch (RuntimeException $error) {
            return back()->with('toast', ['type' => 'error', 'message' => $error->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Número de WhatsApp desvinculado.']);
    }
}
