<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Reloj de las pruebas
|--------------------------------------------------------------------------
|
| La aplicación calcula en UTC pero deriva los días de calendario en hora de
| Lima (ver `App\Services\RelojOperativo`). Entre las 19:00 y la medianoche de
| Lima las dos zonas están en días distintos, así que una prueba que corriera
| en ese rato vería «hoy» distinto según qué reloj mirara y fallaría sola.
|
| Congelarlas a mediodía elimina esa ambigüedad: a las 12:00 UTC son las 07:00
| en Lima, el mismo día en ambas zonas, y una fixture escrita como
| `now()->subDay()` significa exactamente «ayer» en los dos marcos.
|
| El borde en sí no queda sin probar: `RelojOperativoTest` se para a propósito
| en la franja donde las zonas discrepan.
|
*/

pest()->beforeEach(function (): void {
    $this->travelTo(now()->setTime(12, 0));
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function actorConRol(string $rol): User
{
    return User::factory()->create()->assignRole($rol);
}

/** Un JWT con forma real (SUNAT no se verifica acá, solo se lee su `exp`). */
function tokenSol(int $venceEn = 3600): string
{
    $parte = fn (array $datos): string => rtrim(strtr(base64_encode(json_encode($datos)), '+/', '-_'), '=');

    return $parte(['alg' => 'RS256']).'.'.$parte(['exp' => time() + $venceEn, 'sub' => '20364000643']).'.firma';
}

/**
 * Simula el recorrido de login de SOL tal como quedó en la grabación.
 *
 * @param  array<string, mixed>  $api  respuestas de api-cpe por fragmento de ruta
 */
function fingirSol(string $token, bool $loginAcepta = true, array $api = []): void
{
    $seguridad = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/abc/oauth2';

    Http::fake(function (Request $request) use ($token, $loginAcepta, $api, $seguridad) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'api-cpe.sunat.gob.pe') => (function () use ($url, $api) {
                foreach ($api as $fragmento => $respuesta) {
                    if (str_contains($url, $fragmento)) {
                        return is_callable($respuesta) ? $respuesta() : $respuesta;
                    }
                }

                return Http::response(['errors' => [['cod' => 404, 'msg' => 'no']]], 422);
            })(),
            str_contains($url, '/oauth2/authen') => Http::response('', 302, [
                'Location' => "{$seguridad}/loginMenuSol?lang=es-PE&state=ESTADO123",
                'Set-Cookie' => 'TS019e7fc2=seg; Path=/',
            ]),
            str_contains($url, '/loginMenuSol') => Http::response('<form name="LoginForm"></form>'),
            str_contains($url, '/j_security_check') => $loginAcepta
                ? Http::response('', 302, ['Location' => 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/AutenticaMenuInternet.htm?state=x&code=CODIGO'])
                : Http::response('<form name="LoginForm">Usuario o clave incorrectos</form>'),
            str_contains($url, 'AutenticaMenuInternet') => Http::response('', 302, [
                'Location' => 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm?pestana=*&agrupacion=*',
                'Set-Cookie' => 'ITMENUSESSION=sesion; Path=/',
            ]),
            str_contains($url, 'action=execute') => Http::response('', 302, [
                'Location' => "https://e-factura.sunat.gob.pe/app/emitirgre.html?token={$token}",
            ]),
            str_contains($url, 'MenuInternet.htm') => Http::response(
                "<script>redirect(\"{$seguridad}/authen?redirect_uri=x&state=ESTADO123\");</script>",
            ),
        };
    });
}
