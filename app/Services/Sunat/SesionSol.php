<?php

namespace App\Services\Sunat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * La sesión de Paty en SOL, reducida a lo único que hace falta: el token con
 * el que el formulario «Emisión de GRE» de SOL habla con `api-cpe.sunat.gob.pe`.
 *
 * No es una API documentada: es el mismo camino que recorre el navegador
 * (login → menú → abrir la opción → el formulario recibe el token en la URL),
 * copiado de una grabación real del 28-09-2026. El token es un JWT que dura
 * 60 minutos; se guarda en caché hasta poco antes de vencer y se renueva solo
 * cuando alguien lo pide, no con un cron, para no iniciar sesión en SOL 24
 * veces al día sin necesidad.
 *
 * Un login fallido NUNCA se reintenta: el formulario de SOL tiene un captcha
 * que se activa tras intentos fallidos, y reintentar en bucle lo dispararía
 * (o bloquearía al usuario).
 */
class SesionSol
{
    private const CLAVE_CACHE = 'sunat.sol.token';

    /** Margen antes del vencimiento real, para no usar un token a punto de morir. */
    private const MARGEN_SEGUNDOS = 300;

    private const MENU = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';

    /** Código de la opción Empresas → GRE → Emisión de GRE → Emisión de GRE. */
    private const OPCION_EMISION_GRE = '62.1.1.1.1';

    public const AGENTE = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    /**
     * Cookies de la sesión en curso, por nombre. Los dominios de SUNAT no
     * repiten nombres entre sí, así que no hace falta separarlas por host.
     *
     * @var array<string, string>
     */
    private array $cookies = [];

    /** El token vigente; inicia sesión solo si no hay uno en caché. */
    public function token(): string
    {
        $token = Cache::get(self::CLAVE_CACHE);

        if (is_string($token)) {
            return $token;
        }

        // Dos pedidos simultáneos no deben iniciar dos sesiones: el segundo
        // espera al primero y usa su token.
        return Cache::lock(self::CLAVE_CACHE.'.login', 60)->block(45, function (): string {
            $token = Cache::get(self::CLAVE_CACHE);

            return is_string($token) ? $token : $this->iniciarSesion();
        });
    }

    /** Descarta el token en caché (SUNAT lo rechazó antes de tiempo). */
    public function olvidar(): void
    {
        Cache::forget(self::CLAVE_CACHE);
    }

    /** Hace el login completo y deja el token en caché. */
    public function iniciarSesion(): string
    {
        $ruc = (string) config('services.sunat_sol.ruc');
        $usuario = (string) config('services.sunat_sol.usuario');
        $clave = (string) config('services.sunat_sol.clave');

        if ($ruc === '' || $usuario === '' || $clave === '') {
            throw new RuntimeException('Faltan SUNAT_SOL_RUC, SUNAT_SOL_USUARIO o SUNAT_SOL_CLAVE en el .env.');
        }

        $this->cookies = [];

        try {
            // 1. El menú devuelve una página que redirige (por JS) al login OAuth.
            $menu = $this->get(self::MENU);

            if (! preg_match('/redirect\("([^"]+)"\)/', $menu->body(), $coincidencia)) {
                throw new RuntimeException('SOL cambió la página de entrada: no se encontró la redirección al login.');
            }

            // 2. authen → 302 → formulario de login. El `state` viaja en la URL.
            $urlLogin = $this->seguir($coincidencia[1]);
            parse_str((string) parse_url($urlLogin, PHP_URL_QUERY), $parametros);
            $state = $parametros['state'] ?? null;

            if (! is_string($state) || $state === '') {
                throw new RuntimeException('SOL cambió el login: no llegó el parámetro state.');
            }

            // 3. El POST del formulario. `tipo=2` es el ingreso con RUC + usuario.
            $accion = Str::before($urlLogin, '/loginMenuSol').'/j_security_check';
            $respuesta = $this->cliente()->asForm()->post($accion, [
                'tipo' => '2',
                'dni' => '',
                'custom_ruc' => $ruc,
                'j_username' => $usuario,
                'j_password' => $clave,
                'captcha' => '',
                'originalUrl' => 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/AutenticaMenuInternet.htm',
                'lang' => 'es-PE',
                'state' => $state,
            ]);
            $this->guardarCookies($respuesta);

            $destino = (string) $respuesta->header('Location');

            if (! $respuesta->redirect() || ! str_contains($destino, 'code=')) {
                throw new RuntimeException('SOL rechazó el usuario o la clave (o pidió captcha). No se reintenta para no bloquear al usuario.');
            }

            // 4. AutenticaMenuInternet canjea el code por la sesión del menú.
            $this->seguir($destino);

            // 5. Abrir la opción de emisión: SOL redirige al formulario con el token.
            $this->cliente()->asForm()->post(self::MENU, ['action' => 'prevApp']);
            $apertura = $this->cliente()->get(self::MENU, [
                'action' => 'execute',
                'code' => self::OPCION_EMISION_GRE,
                's' => 'ww1',
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con SUNAT. Revisa si SOL está en línea.');
        }

        parse_str((string) parse_url((string) $apertura->header('Location'), PHP_URL_QUERY), $parametros);
        $token = $parametros['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('SOL inició sesión pero no entregó el token de Emisión de GRE.');
        }

        Cache::put(self::CLAVE_CACHE, $token, $this->segundosDeVida($token));
        Log::channel('sunat')->info('SUNAT SOL: sesión iniciada para la emisión de GRE.');

        return $token;
    }

    /** Segundos que el token puede quedar en caché, según su `exp`. */
    private function segundosDeVida(string $token): int
    {
        $partes = explode('.', $token);
        $datos = json_decode((string) base64_decode(strtr($partes[1] ?? '', '-_', '+/')), true);
        $vence = is_array($datos) && is_int($datos['exp'] ?? null) ? $datos['exp'] : time() + 3600;

        return max(60, $vence - time() - self::MARGEN_SEGUNDOS);
    }

    /** GET que sigue las redirecciones a mano, guardando cookies; devuelve la URL final. */
    private function seguir(string $url): string
    {
        for ($saltos = 0; $saltos < 10; $saltos++) {
            $respuesta = $this->get($url);

            if (! $respuesta->redirect()) {
                return $url;
            }

            $url = (string) $respuesta->header('Location');
        }

        throw new RuntimeException('SOL entró en un bucle de redirecciones.');
    }

    private function get(string $url): Response
    {
        $respuesta = $this->cliente()->get($url);
        $this->guardarCookies($respuesta);

        return $respuesta;
    }

    private function guardarCookies(Response $respuesta): void
    {
        foreach ($respuesta->headers()['Set-Cookie'] ?? $respuesta->headers()['set-cookie'] ?? [] as $linea) {
            [$par] = explode(';', $linea, 2);
            [$nombre, $valor] = array_pad(explode('=', $par, 2), 2, '');
            $this->cookies[trim($nombre)] = trim($valor);
        }
    }

    private function cliente(): PendingRequest
    {
        $cookies = collect($this->cookies)->map(fn (string $valor, string $nombre): string => "{$nombre}={$valor}")->implode('; ');

        return Http::withoutRedirecting()
            ->timeout(30)
            ->withUserAgent(self::AGENTE)
            ->withHeaders(array_filter(['Cookie' => $cookies]));
    }
}
