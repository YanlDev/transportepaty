<?php

namespace App\Http\Controllers;

use App\Enums\TipoAvisoSalida;
use App\Models\AreaAviso;
use App\Models\Programacion;
use App\Services\AvisoDeSalida;
use App\Services\AvisosPorWhatsapp;
use App\Services\Imagenes\ImagenesDeAviso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Los avisos de una salida como imagen: la vista previa que se mira antes de
 * mandar, y el envío, que se pone en la cola y lo manda un worker (ver
 * `AvisosPorWhatsapp`). Al conductor (aviso y advertencia) y a las áreas.
 */
class AvisoSalidaController extends Controller
{
    public function __construct(
        private readonly ImagenesDeAviso $imagenes,
        private readonly AvisosPorWhatsapp $avisos,
        private readonly AvisoDeSalida $aviso,
    ) {}

    public function imagen(Programacion $programacion, TipoAvisoSalida $tipo): Response
    {
        $this->authorize('update', $programacion);

        $programacion->loadMissing(['vehiculo', 'conductor', 'cliente']);

        return $this->png(match ($tipo) {
            TipoAvisoSalida::Conductor => $this->imagenes->conductor($programacion),
            TipoAvisoSalida::Advertencia => $this->imagenes->advertencia(),
        });
    }

    public function enviar(Request $request, Programacion $programacion, TipoAvisoSalida $tipo): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $datos = $request->validate([
            'numero' => ['required', 'string', 'max:20'],
            // Cuál de sus números: «Conductor», «Alterno», «Adicional».
            'destino' => ['nullable', 'string', 'max:30'],
        ]);

        $numero = $this->aviso->numeroWhatsapp($datos['numero']);

        if ($numero === null) {
            return back()->withErrors(['numero' => 'El número no es válido.']);
        }

        $this->avisos->encolarAlConductor($programacion, $tipo, $numero, $datos['destino'] ?? 'Conductor', $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Enviando por WhatsApp a {$numero}…"]);
    }

    public function imagenArea(Programacion $programacion, AreaAviso $area): Response
    {
        $this->authorize('update', $programacion);

        $programacion->loadMissing(['vehiculo', 'conductor', 'cliente']);

        return $this->png($this->imagenes->area($programacion, $area));
    }

    /**
     * Va siempre al número guardado del área, no a uno que llegue en la
     * petición: el destino de un aviso de área no se elige al enviar.
     */
    public function enviarArea(Request $request, Programacion $programacion, AreaAviso $area): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $numero = $this->aviso->numeroWhatsapp($area->numero);

        if (! $area->activa || $numero === null) {
            return back()->with('toast', ['type' => 'error', 'message' => "{$area->nombre} no tiene un número activo."]);
        }

        $this->avisos->encolarAlArea($programacion, $area, $numero, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Enviando a {$area->nombre}…"]);
    }

    private function png(string $imagen): Response
    {
        return response($imagen, 200, [
            'Content-Type' => 'image/png',
            // Cambia con cada edición de la programación: no se guarda.
            'Cache-Control' => 'no-store',
        ]);
    }
}
