<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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
    /** Cuánto se recuerda si el número está conectado, en segundos. */
    private const SEGUNDOS_EN_CACHE = 15;

    private const CLAVE_CACHE = 'whatsapp.conectado';

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
            // Es una consulta local que tarda milisegundos: si no responde
            // en un par de segundos, el proceso está trabado y se trata
            // como caído en vez de colgar la página.
            $respuesta = $this->cliente(timeout: 2)->get('/estado');
        } catch (ConnectionException) {
            return $this->sinServicio();
        }

        if ($respuesta->failed()) {
            return $this->sinServicio();
        }

        $estado = [
            'estado' => $respuesta->json('estado'),
            'qr' => $respuesta->json('qr'),
            'numero' => $respuesta->json('numero'),
        ];

        // Cada lectura fresca (la del panel, que se refresca sola mientras
        // se vincula) deja al día lo que recuerda `conectado()`.
        Cache::put(self::CLAVE_CACHE, $estado['estado'] === 'conectado', self::SEGUNDOS_EN_CACHE);

        return $estado;
    }

    /**
     * Si el número está listo para mandar. Lo pregunta Programación en cada
     * carga, así que se recuerda unos segundos: un proceso trabado cuesta a
     * lo sumo una espera corta cada tanto, no una por visita.
     */
    public function conectado(): bool
    {
        return Cache::remember(
            self::CLAVE_CACHE,
            self::SEGUNDOS_EN_CACHE,
            fn (): bool => $this->estado()['estado'] === 'conectado',
        );
    }

    /**
     * Arranca la vinculación. Sin teléfono se vincula escaneando el QR que
     * después trae `estado()`; con teléfono, WhatsApp devuelve un código de
     * 8 caracteres para escribir en el celular.
     */
    public function vincular(?string $telefono = null): ?string
    {
        Cache::forget(self::CLAVE_CACHE);

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
        Cache::forget(self::CLAVE_CACHE);
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

    /**
     * @return array{estado: 'sin_servicio', qr: null, numero: null}
     */
    private function sinServicio(): array
    {
        Cache::put(self::CLAVE_CACHE, false, self::SEGUNDOS_EN_CACHE);

        return ['estado' => 'sin_servicio', 'qr' => null, 'numero' => null];
    }

    private function cliente(int $timeout = 25): PendingRequest
    {
        return Http::baseUrl((string) config('transpaty.whatsapp.url'))
            ->withToken((string) config('transpaty.whatsapp.token'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(min(3, $timeout))
            // Vincular por código espera a que WhatsApp negocie; mandar
            // espera la confirmación del envío.
            ->timeout($timeout);
    }
}
