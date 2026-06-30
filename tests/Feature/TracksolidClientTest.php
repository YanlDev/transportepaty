<?php

use App\Services\Tracksolid\TracksolidClient;
use App\Services\Tracksolid\TracksolidException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const BASE_URL = 'https://api.test/route/rest';

function cliente(): TracksolidClient
{
    return new TracksolidClient(
        baseUrl: BASE_URL,
        appKey: 'APPKEY',
        appSecret: 'SECRET',
        account: 'cuenta',
        passwordMd5: 'pwdmd5',
    );
}

function respuestaOk(array $result): PromiseInterface
{
    return Http::response(['code' => 0, 'message' => 'success', 'result' => $result]);
}

it('obtains a token then lists devices and caches the token', function (): void {
    Http::fake(function (Request $request) {
        return match ($request->data()['method'] ?? null) {
            'jimi.oauth.token.get' => respuestaOk([
                'accessToken' => 'TKN-123',
                'expiresIn' => 7200,
                'refreshToken' => 'R',
            ]),
            'jimi.user.device.list' => respuestaOk([
                ['imei' => '868', 'vehicleNumber' => 'ABC-123'],
            ]),
            default => Http::response(['code' => 1001, 'message' => 'unknown'], 200),
        };
    });

    $client = cliente();

    $dispositivos = $client->listDevices();

    expect($dispositivos)->toHaveCount(1)
        ->and($dispositivos->first()['imei'])->toBe('868')
        ->and(cache()->get('tracksolid:access_token'))->toBe('TKN-123');

    // Segunda llamada reutiliza el token cacheado (no vuelve a pedir token).
    $client->listDevices();

    Http::assertSentCount(3); // token + 2 device list
});

it('returns the live camera page url from UrlCamera', function (): void {
    Http::fake(function (Request $request) {
        return match ($request->data()['method'] ?? null) {
            'jimi.oauth.token.get' => respuestaOk(['accessToken' => 'TKN', 'expiresIn' => 7200]),
            'jimi.device.live.page.url' => respuestaOk(['UrlCamera' => 'https://us.tracksolidpro.com/video/xyz']),
            default => Http::response(['code' => 1001, 'message' => 'unknown'], 200),
        };
    });

    expect(cliente()->liveVideoUrl('868', 2))
        ->toBe('https://us.tracksolidpro.com/video/xyz');
});

it('signs requests with a 32-char uppercase md5', function (): void {
    Http::fake(fn () => respuestaOk([]));

    cliente()->getAccessToken();

    Http::assertSent(function (Request $request): bool {
        $sign = $request->data()['sign'] ?? '';

        return is_string($sign) && preg_match('/^[A-F0-9]{32}$/', $sign) === 1;
    });
});

it('throws a TracksolidException on a non-zero business code', function (): void {
    Http::fake(function (Request $request) {
        return ($request->data()['method'] ?? null) === 'jimi.oauth.token.get'
            ? respuestaOk(['accessToken' => 'TKN', 'expiresIn' => 7200])
            : Http::response(['code' => 1112, 'message' => 'device error'], 200);
    });

    expect(fn () => cliente()->listDevices())
        ->toThrow(TracksolidException::class, 'device error');
});

it('refreshes the token once when the API reports an invalid token', function (): void {
    $deviceCalls = 0;

    Http::fake(function (Request $request) use (&$deviceCalls) {
        if (($request->data()['method'] ?? null) === 'jimi.oauth.token.get') {
            return respuestaOk(['accessToken' => 'TKN', 'expiresIn' => 7200]);
        }

        $deviceCalls++;

        return $deviceCalls === 1
            ? Http::response(['code' => 1100, 'message' => 'token is invalid'], 200)
            : respuestaOk([['imei' => '999']]);
    });

    $dispositivos = cliente()->listDevices();

    expect($dispositivos)->toHaveCount(1);

    // token.get x2 (inicial + tras invalidar) + device.list x2
    Http::assertSentCount(4);
});
