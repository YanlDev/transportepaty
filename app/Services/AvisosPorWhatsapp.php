<?php

namespace App\Services;

use App\Enums\EstadoEnvio;
use App\Enums\TipoAvisoSalida;
use App\Jobs\EnviarAvisoWhatsapp;
use App\Models\AreaAviso;
use App\Models\EnvioWhatsapp;
use App\Models\Programacion;
use App\Models\User;
use App\Services\Imagenes\ImagenesDeAviso;
use RuntimeException;

/**
 * Los avisos de una salida por el número de la empresa: se anotan, se ponen
 * en la cola y un worker los manda. Así el botón responde al instante y un
 * envío lento de WhatsApp no ocupa la app de nadie.
 */
class AvisosPorWhatsapp
{
    public function __construct(
        private readonly ImagenesDeAviso $imagenes,
        private readonly WhatsappServicio $whatsapp,
    ) {}

    /** Anota el aviso al conductor (o la advertencia) y lo pone en la cola. */
    public function encolarAlConductor(Programacion $programacion, TipoAvisoSalida $tipo, string $numero, string $destino, ?User $usuario): EnvioWhatsapp
    {
        return $this->encolar([
            'programacion_id' => $programacion->id,
            'tipo' => $tipo->value,
            'destino' => $destino,
            'numero' => $numero,
            'enviado_por' => $usuario?->id,
        ]);
    }

    /** Anota el aviso a un área y lo pone en la cola, a su número guardado. */
    public function encolarAlArea(Programacion $programacion, AreaAviso $area, string $numero, ?User $usuario): EnvioWhatsapp
    {
        return $this->encolar([
            'programacion_id' => $programacion->id,
            'area_aviso_id' => $area->id,
            'tipo' => EnvioWhatsapp::TIPO_AREA,
            'destino' => $area->nombre,
            'numero' => $numero,
            'enviado_por' => $usuario?->id,
        ]);
    }

    /**
     * Lo que hace el worker: arma la imagen con los datos de ese momento, la
     * manda y anota el id que le dio WhatsApp. Si falla, la excepción sube
     * para que la cola reintente.
     */
    public function mandar(EnvioWhatsapp $envio): void
    {
        $programacion = $envio->programacion;

        if ($programacion === null) {
            throw new RuntimeException('La programación ya no existe.');
        }

        $programacion->loadMissing(['vehiculo', 'conductor', 'cliente']);

        $mensajeId = $this->whatsapp->enviar($envio->numero, $this->leyenda($envio, $programacion), $this->imagen($envio, $programacion));

        $envio->update([
            'estado' => EstadoEnvio::Enviado,
            'mensaje_id' => $mensajeId,
            'error' => null,
            'enviado_at' => now(),
        ]);

        // Lo que respalda ante una multa es habérselo dicho al conductor:
        // esos envíos dejan la salida como avisada.
        if ($envio->tipo !== EnvioWhatsapp::TIPO_AREA) {
            $programacion->update([
                'aviso_enviado_at' => now(),
                'aviso_enviado_por' => $envio->enviado_por,
            ]);
        }
    }

    /** Cuando la cola se rinde después de los reintentos. */
    public function marcarFallido(EnvioWhatsapp $envio, string $error): void
    {
        $envio->update(['estado' => EstadoEnvio::Fallido, 'error' => $error]);
    }

    private function imagen(EnvioWhatsapp $envio, Programacion $programacion): string
    {
        return match ($envio->tipo) {
            EnvioWhatsapp::TIPO_CONDUCTOR => $this->imagenes->conductor($programacion),
            EnvioWhatsapp::TIPO_ADVERTENCIA => $this->imagenes->advertencia(),
            default => $this->imagenes->area(
                $programacion,
                $envio->area ?? throw new RuntimeException('El área ya no existe.'),
            ),
        };
    }

    /**
     * La leyenda corta que acompaña a la imagen: es lo que se lee en la
     * notificación del celular antes de abrir el chat.
     */
    private function leyenda(EnvioWhatsapp $envio, Programacion $programacion): string
    {
        $fecha = $programacion->fecha->format('d/m');
        $placa = $programacion->vehiculo->placa;

        return match ($envio->tipo) {
            EnvioWhatsapp::TIPO_CONDUCTOR => "Programación {$fecha} · {$placa}",
            EnvioWhatsapp::TIPO_ADVERTENCIA => 'Documentación obligatoria antes de salir',
            default => "Unidad programada {$fecha} · {$placa}",
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function encolar(array $datos): EnvioWhatsapp
    {
        $envio = EnvioWhatsapp::query()->create([...$datos, 'estado' => EstadoEnvio::Pendiente]);

        EnviarAvisoWhatsapp::dispatch($envio);

        return $envio;
    }
}
