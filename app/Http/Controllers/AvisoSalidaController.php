<?php

namespace App\Http\Controllers;

use App\Enums\TipoAvisoSalida;
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
 * mandar y el envío por el número de WhatsApp de la empresa.
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

        return response($this->generar($programacion, $tipo), 200, [
            'Content-Type' => 'image/png',
            // Cambia con cada edición de la programación: no se guarda.
            'Cache-Control' => 'no-store',
        ]);
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

        try {
            $this->whatsapp->enviar($numero, $tipo->leyenda($programacion), $this->generar($programacion, $tipo));
        } catch (RuntimeException $error) {
            return back()->with('toast', ['type' => 'error', 'message' => $error->getMessage()]);
        }

        // Lo que respalda ante una multa es habérselo dicho al conductor:
        // solo esos envíos dejan la salida como avisada.
        if ($tipo->marcaComoAvisado()) {
            $programacion->update([
                'aviso_enviado_at' => now(),
                'aviso_enviado_por' => $request->user()?->id,
            ]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => "Aviso enviado por WhatsApp a {$numero}."]);
    }

    private function generar(Programacion $programacion, TipoAvisoSalida $tipo): string
    {
        $programacion->loadMissing(['vehiculo', 'conductor', 'cliente']);

        return match ($tipo) {
            TipoAvisoSalida::Conductor => $this->imagenes->conductor($programacion),
            TipoAvisoSalida::Advertencia => $this->imagenes->advertencia(),
            TipoAvisoSalida::Abastecimiento => $this->imagenes->abastecimiento($programacion),
            TipoAvisoSalida::Facturacion => $this->imagenes->facturacion($programacion),
        };
    }
}
