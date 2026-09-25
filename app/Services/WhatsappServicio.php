<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente del servicio de WhatsApp (`whatsapp/servidor.js`), el proceso Node
 * que tiene vinculado el número de la empresa.
 *
 * Los errores salen como `RuntimeException` con un mensaje para mostrarle a
 * quien usa la app: «no está conectado», «ese número no tiene WhatsApp».
 */
class WhatsappServicio
{
    /**
     * Si el servicio responde y en qué punto está la vinculación. Nunca
     * falla: con el proceso caído devuelve `sin_servicio`, que la pantalla
     * muestra como tal en vez de reventar.
     *
     * @return array{estado: 'sin_servicio'|'desconectado'|'vinculando'|'conectado', qr: string|null, numero: string|null}
     */
    public function estado(): array
    {
        try {
            $respuesta = $this->cliente()->get('/estado');
        } catch (ConnectionException) {
            return ['estado' => 'sin_servicio', 'qr' => null, 'numero' => null];
        }

        if ($respuesta->failed()) {
            return ['estado' => 'sin_servicio', 'qr' => null, 'numero' => null];
        }

        return [
            'estado' => $respuesta->json('estado'),
            'qr' => $respuesta->json('qr'),
            'numero' => $respuesta->json('numero'),
        ];
    }

    /**
     * Arranca la vinculación. Sin teléfono se vincula escaneando el QR que
     * después trae `estado()`; con teléfono, WhatsApp devuelve un código de
     * 8 caracteres para escribir en el celular.
     */
    public function vincular(?string $telefono = null): ?string
    {
        return $this->pedir('post', '/vincular', ['telefono' => $telefono])->json('codigo');
    }

    /**
     * Manda un texto, o una imagen con el texto como leyenda. Devuelve el id
     * del mensaje en WhatsApp.
     */
    public function enviar(string $numero, string $texto, ?string $imagenBinaria = null): ?string
    {
        return $this->pedir('post', '/enviar', [
            'numero' => $numero,
            'texto' => $texto,
            'imagen' => $imagenBinaria === null ? null : base64_encode($imagenBinaria),
        ])->json('id');
    }

    /** Cierra la sesión del número en WhatsApp y la borra del servidor. */
    public function desvincular(): void
    {
        $this->pedir('post', '/desvincular');
    }

    /**
     * @param  'get'|'post'  $metodo
     * @param  array<string, mixed>  $datos
     */
    private function pedir(string $metodo, string $ruta, array $datos = []): Response
    {
        try {
            $respuesta = $this->cliente()->{$metodo}($ruta, $datos);
        } catch (ConnectionException) {
            throw new RuntimeException('El servicio de WhatsApp no está corriendo en el servidor.');
        }

        if ($respuesta->failed()) {
            throw new RuntimeException($respuesta->json('error') ?? 'WhatsApp no pudo completar la operación.');
        }

        return $respuesta;
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl((string) config('transpaty.whatsapp.url'))
            ->withToken((string) config('transpaty.whatsapp.token'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            // Vincular por código espera a que WhatsApp negocie; mandar
            // espera la confirmación del envío.
            ->timeout(25);
    }
}
