<?php

use App\Services\Sunat\ClienteGreSunat;
use App\Services\Sunat\SesionSol;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.sunat_sol.ruc' => '20364000643',
        'services.sunat_sol.usuario' => 'PATYGRE',
        'services.sunat_sol.clave' => 'secreta',
    ]);
});

it('inicia sesión en SOL, saca el token de la URL del formulario y lo reutiliza', function () {
    $token = tokenSol();
    fingirSol($token);

    expect(app(SesionSol::class)->token())->toBe($token)
        ->and(app(SesionSol::class)->token())->toBe($token);

    // Un solo login pese a pedir el token dos veces.
    Http::assertSentCount(8);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'j_security_check')
        && $request['custom_ruc'] === '20364000643'
        && $request['j_username'] === 'PATYGRE'
        && $request['j_password'] === 'secreta'
        && $request['state'] === 'ESTADO123'
        && $request['captcha'] === '');

    // La cookie de sesión del menú viaja al abrir la opción de emisión.
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'code=62.1.1.1.1')
        && str_contains($request->header('Cookie')[0] ?? '', 'ITMENUSESSION=sesion'));
});

it('no reintenta cuando SOL rechaza el usuario o la clave', function () {
    fingirSol(tokenSol(), loginAcepta: false);

    expect(fn () => app(SesionSol::class)->token())
        ->toThrow(RuntimeException::class, 'rechazó el usuario o la clave');

    Http::assertSentCount(4);
});

it('pide los datos de SOL antes de intentar entrar', function () {
    config(['services.sunat_sol.clave' => null]);
    Http::fake();

    expect(fn () => app(SesionSol::class)->token())->toThrow(RuntimeException::class, 'SUNAT_SOL_CLAVE');

    Http::assertNothingSent();
});

it('trae la GR-remitente completa cuando Paty es el transportista', function () {
    fingirSol(tokenSol(), api: [
        '/gre/comprobantes/20100136741-09-T007-10088' => Http::response(['numSerie' => 'T007', 'numCpe' => 10088]),
    ]);

    $guia = app(ClienteGreSunat::class)->guiaRemitente('20100136741', 't007', 10088);

    expect($guia)->toBe(['completa' => true, 'datos' => ['numSerie' => 'T007', 'numCpe' => 10088]]);
});

it('cae a la versión resumida cuando SUNAT no deja consultar la GR completa', function () {
    fingirSol(tokenSol(), api: [
        '/simplificado/20100136741-09-T007-1089' => Http::response(['transportista' => ['numDocIdentidad' => '20100228191']]),
        '/gre/comprobantes/20100136741-09-T007-1089' => Http::response(['errors' => [['cod' => 2049, 'msg' => 'Usted no puede consultar este comprobante']]], 422),
    ]);

    $guia = app(ClienteGreSunat::class)->guiaRemitente('20100136741', 'T007', 1089);

    expect($guia['completa'])->toBeFalse()
        ->and($guia['datos']['transportista']['numDocIdentidad'])->toBe('20100228191');
});

it('devuelve el mensaje de SUNAT cuando no encuentra la licencia', function () {
    fingirSol(tokenSol(), api: [
        '/numLicencia' => Http::response(['errors' => [['cod' => 2037, 'msg' => 'No encontramos en nuestros sistemas el número de licencia de conducir ingresado']]], 422),
        '/TCK922/numPlaca' => Http::response(['numTucChv' => '21M22000519E', 'indTucChv' => '2', 'indVigencia' => '1']),
    ]);

    $sunat = app(ClienteGreSunat::class);

    expect($sunat->licencia('D43205379'))->toMatchArray(['encontrada' => false, 'mensaje' => 'No encontramos en nuestros sistemas el número de licencia de conducir ingresado'])
        ->and($sunat->placa('TCK-922')['numTucChv'])->toBe('21M22000519E');
});

it('inicia sesión de nuevo una vez si SUNAT rechaza el token antes de tiempo', function () {
    $respuestas = [Http::response(null, 401), Http::response(['numTucChv' => '21M22000519E'])];
    fingirSol(tokenSol(), api: [
        '/numPlaca' => function () use (&$respuestas) {
            return array_shift($respuestas);
        },
    ]);

    expect(app(ClienteGreSunat::class)->placa('TCK922')['numTucChv'])->toBe('21M22000519E');

    // login (8) + consulta rechazada + login de nuevo (8) + consulta.
    Http::assertSentCount(18);
});
