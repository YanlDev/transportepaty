<?php

namespace App\Http\Controllers;

use App\Enums\TipoAvisoSalida;
use App\Models\AreaAviso;
use App\Models\Programacion;
use App\Services\AvisoDeSalida;
use App\Services\Imagenes\ImagenesDeAviso;
use App\Services\WhatsappServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Los avisos de una salida como imagen: la vista previa que se mira antes de
 * mandar y el envío por el número de WhatsApp de la empresa. Al conductor
 * (aviso y advertencia) y a las áreas de la casa.
 */
class AvisoSalidaController extends Controller
{
    public function __construct(
        private readonly ImagenesDeAviso $imagenes,
        private readonly WhatsappServicio $whatsapp,
        private readonly AvisoDeSalida $aviso,
    ) {}

    public function imagen(Programacion $programacion, TipoAvisoSalida $tipo): Response
    {
        $this->authorize('update', $programacion);

        return $this->png($this->imagenDelConductor($programacion, $tipo));
    }

    public function enviar(Request $request, Programacion $programacion, TipoAvisoSalida $tipo): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $numero = $this->aviso->numeroWhatsapp($request->validate([
            'numero' => ['required', 'string', 'max:20'],
        ])['numero']);

        if ($numero === null) {
            return back()->withErrors(['numero' => 'El número no es válido.']);
        }

        $error = $this->mandar($numero, $tipo->leyenda($programacion), $this->imagenDelConductor($programacion, $tipo));

        if ($error !== null) {
            return $error;
        }

        // Lo que respalda ante una multa es habérselo dicho al conductor:
        // estos envíos dejan la salida como avisada.
        $programacion->update([
            'aviso_enviado_at' => now(),
            'aviso_enviado_por' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Aviso enviado por WhatsApp a {$numero}."]);
    }

    public function imagenArea(Programacion $programacion, AreaAviso $area): Response
    {
        $this->authorize('update', $programacion);

        return $this->png($this->imagenDelArea($programacion, $area));
    }

    /**
     * Va siempre al número guardado del área, no a uno que llegue en la
     * petición: el destino de un aviso de área no se elige al enviar.
     */
    public function enviarArea(Programacion $programacion, AreaAviso $area): RedirectResponse
    {
        $this->authorize('update', $programacion);

        $numero = $this->aviso->numeroWhatsapp($area->numero);

        if (! $area->activa || $numero === null) {
            return back()->with('toast', ['type' => 'error', 'message' => "{$area->nombre} no tiene un número activo."]);
        }

        $leyenda = sprintf('Unidad programada %s · %s', $programacion->fecha->format('d/m'), $programacion->vehiculo->placa);

        return $this->mandar($numero, $leyenda, $this->imagenDelArea($programacion, $area))
            ?? back()->with('toast', ['type' => 'success', 'message' => "Aviso enviado a {$area->nombre}."]);
    }

    /** Manda la imagen; si WhatsApp falla, devuelve la respuesta con el error. */
    private function mandar(string $numero, string $leyenda, string $imagen): ?RedirectResponse
    {
        try {
            $this->whatsapp->enviar($numero, $leyenda, $imagen);
        } catch (RuntimeException $error) {
            return back()->with('toast', ['type' => 'error', 'message' => $error->getMessage()]);
        }

        return null;
    }

    private function imagenDelConductor(Programacion $programacion, TipoAvisoSalida $tipo): string
    {
        $programacion->loadMissing(['vehiculo', 'conductor', 'cliente']);

        return match ($tipo) {
            TipoAvisoSalida::Conductor => $this->imagenes->conductor($programacion),
            TipoAvisoSalida::Advertencia => $this->imagenes->advertencia(),
        };
    }

    private function imagenDelArea(Programacion $programacion, AreaAviso $area): string
    {
        $programacion->loadMissing(['vehiculo', 'conductor', 'cliente']);

        return $this->imagenes->area($programacion, $area);
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
