<?php

namespace App\Services\Tracksolid;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para la Open API de Tracksolid / JIMI.
 *
 * Maneja la firma de peticiones, el token de acceso (con caché y re-obtención
 * antes de expirar) y expone los métodos de la API que usa la flota.
 *
 * @see https://tracksolidprodocs.jimicloud.com/integration/integration.html
 */
class TracksolidClient
{
    private const VERSION = '1.0';

    private const TOKEN_CACHE_KEY = 'tracksolid:access_token';

    /** Tiempo de vida solicitado para el token (segundos, máx. 7200). */
    private const TOKEN_TTL = 7200;

    /** Margen para renovar el token antes de su expiración real (segundos). */
    private const TOKEN_REFRESH_MARGIN = 120;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $account,
        private readonly string $passwordMd5,
    ) {}

    /**
     * Construye el cliente desde config/services.php.
     */
    public static function fromConfig(): self
    {
        /** @var array<string, string|null> $config */
        $config = config('services.tracksolid');

        $passwordMd5 = $config['password_md5']
            ?: md5((string) ($config['password'] ?? ''));

        return new self(
            baseUrl: (string) $config['base_url'],
            appKey: (string) $config['app_key'],
            appSecret: (string) $config['app_secret'],
            account: (string) $config['account'],
            passwordMd5: $passwordMd5,
        );
    }

    /**
     * Lista todos los dispositivos de una cuenta (por defecto la propia).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listDevices(?string $account = null): Collection
    {
        $result = $this->call('jimi.user.device.list', [
            'target' => $account ?? $this->account,
        ]);

        return collect(is_array($result) ? $result : []);
    }

    /**
     * Detalle de un dispositivo por IMEI.
     *
     * @return array<string, mixed>
     */
    public function deviceDetail(string $imei): array
    {
        $result = $this->call('jimi.track.device.detail', ['imei' => $imei]);

        return is_array($result) ? $result : [];
    }

    /**
     * Puntos del recorrido (track) de un dispositivo entre dos fechas.
     * Límite del API: rango ≤ 7 días, dentro de los últimos 3 meses.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function trackList(string $imei, string $beginTime, string $endTime): Collection
    {
        $result = $this->call('jimi.device.track.list', [
            'imei' => $imei,
            'begin_time' => $beginTime,
            'end_time' => $endTime,
        ]);

        return collect(is_array($result) ? $result : []);
    }

    /**
     * Última ubicación de uno o varios dispositivos.
     *
     * @param  list<string>  $imeis
     * @return Collection<int, array<string, mixed>>
     */
    public function latestLocations(array $imeis): Collection
    {
        $result = $this->call('jimi.device.location.get', [
            'imeis' => implode(',', $imeis),
        ]);

        return collect(is_array($result) ? $result : []);
    }

    /**
     * URL de video EN VIVO de una sola cámara (limpia, sin mapa): `channel`
     * 1 = carretera, 2 = cabina. Cada llamada genera una URL con token fresco.
     */
    public function liveVideoUrl(string $imei, int $channel = 1): ?string
    {
        return $this->extractCameraUrl($this->call('jimi.device.live.page.url', [
            'imei' => $imei,
            'channel' => $channel,
            'voice' => 1,
        ]));
    }

    /**
     * URL de la consola de VIDEO HISTÓRICO de una dashcam (con selector de
     * fecha y línea de tiempo de grabaciones).
     */
    public function historicVideoUrl(string $imei): ?string
    {
        return $this->extractCameraUrl($this->call('jimi.device.live.page.url', [
            'imei' => $imei,
            'type' => 2,
        ]));
    }

    private function extractCameraUrl(mixed $result): ?string
    {
        $url = is_array($result) ? ($result['UrlCamera'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Token de acceso vigente (cacheado). Lo re-obtiene si caducó.
     */
    public function getAccessToken(): string
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->requestAccessToken();
    }

    /**
     * Solicita un token nuevo y lo guarda en caché hasta poco antes de expirar.
     */
    private function requestAccessToken(): string
    {
        $result = $this->call('jimi.oauth.token.get', [
            'user_id' => $this->account,
            'user_pwd_md5' => $this->passwordMd5,
            'expires_in' => self::TOKEN_TTL,
        ], withToken: false);

        $token = (string) ($result['accessToken'] ?? '');
        $expiresIn = (int) ($result['expiresIn'] ?? self::TOKEN_TTL);
        $ttl = max($expiresIn - self::TOKEN_REFRESH_MARGIN, 60);

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * Ejecuta una petición firmada y devuelve el campo `result`.
     *
     * @param  array<string, scalar>  $params  Parámetros privados del método.
     */
    private function call(string $method, array $params = [], bool $withToken = true, bool $retryOnAuthError = true): mixed
    {
        $payload = [
            'method' => $method,
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'app_key' => $this->appKey,
            'sign_method' => 'md5',
            'v' => self::VERSION,
            'format' => 'json',
            ...$params,
        ];

        if ($withToken) {
            $payload['access_token'] = $this->getAccessToken();
        }

        $payload['sign'] = $this->sign($payload);

        $data = Http::asForm()
            ->timeout(20)
            ->connectTimeout(10)
            ->retry(2, 200, throw: false)
            ->post($this->baseUrl, $payload)
            ->throw()
            ->json();

        $code = (int) ($data['code'] ?? -1);

        if ($code !== 0) {
            // El token pudo expirar antes de tiempo: límpialo y reintenta una vez.
            if ($withToken && $retryOnAuthError && $this->isAuthError($data)) {
                Cache::forget(self::TOKEN_CACHE_KEY);

                return $this->call($method, $params, withToken: true, retryOnAuthError: false);
            }

            throw new TracksolidException(
                (string) ($data['message'] ?? 'Error desconocido de Tracksolid'),
                $code,
            );
        }

        return $data['result'] ?? null;
    }

    /**
     * Firma: md5(appSecret + parámetros ordenados (clave+valor) + appSecret), en mayúsculas.
     *
     * @param  array<string, scalar>  $params
     */
    private function sign(array $params): string
    {
        ksort($params);

        $base = $this->appSecret;

        foreach ($params as $key => $value) {
            $base .= $key.$value;
        }

        $base .= $this->appSecret;

        return strtoupper(md5($base));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isAuthError(array $data): bool
    {
        return str_contains(strtolower((string) ($data['message'] ?? '')), 'token');
    }
}
