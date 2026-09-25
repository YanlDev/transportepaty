<?php

namespace App\Http\Controllers;

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
