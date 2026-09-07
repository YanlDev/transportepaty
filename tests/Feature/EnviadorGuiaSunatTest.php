<?php

use App\Services\EnviadorGuiaSunat;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

beforeEach(function (): void {
    config()->set('gre.emisor.ruc', '20364000643');
    config()->set('gre.api', [
        'client_id' => 'un-client-id',
        'client_secret' => 'un-secreto',
        'usuario_sol' => 'MODDATOS',
        'clave_sol' => 'moddatos',
    ]);
    config()->set('gre.endpoints.api', 'https://api.sunat.gob.pe/v1');
});

/**
 * Un enviador cuyo HTTP responde lo que se le indique, sin salir a la red.
 *
 * @param  list<Response>  $respuestas
 */
function enviadorConRespuestas(array $respuestas, ?array &$peticiones = null): EnviadorGuiaSunat
{
    $mock = new MockHandler($respuestas);
    $stack = HandlerStack::create($mock);
    $peticiones = [];
    $stack->push(Middleware::history($peticiones));

    return new EnviadorGuiaSunat(new Client(['handler' => $stack]));
}

function respuestaJson(array $cuerpo): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode($cuerpo));
}

it('sends the guide zipped and returns the ticket', function (): void {
    $enviador = enviadorConRespuestas([
        respuestaJson(['access_token' => 'token-abc', 'token_type' => 'bearer', 'expires_in' => 3600]),
        respuestaJson(['numTicket' => '202609061234567']),
    ], $peticiones);

    $ticket = $enviador->enviar('20364000643-31-V001-1', '<?xml version="1.0"?><root/>');

    expect($ticket)->toBe('202609061234567');

    // Lo que viaja es un zip en base64 con su hash: si se enviara el XML pelado
    // SUNAT lo rechazaría sin decir por qué.
    $cuerpo = json_decode((string) $peticiones[1]['request']->getBody(), true);
    $zip = base64_decode($cuerpo['archivo']['arcGreZip']);

    expect($cuerpo['archivo']['nomArchivo'])->toBe('20364000643-31-V001-1.zip')
        ->and($cuerpo['archivo']['hashZip'])->toBe(hash('sha256', $zip))
        ->and(substr($zip, 0, 2))->toBe('PK');
});

it('names the XML inside the zip exactly like the document', function (): void {
    $enviador = enviadorConRespuestas([
        respuestaJson(['access_token' => 'token-abc']),
        respuestaJson(['numTicket' => '1']),
    ], $peticiones);

    $enviador->enviar('20364000643-31-V001-9', '<?xml version="1.0"?><root/>');

    $cuerpo = json_decode((string) $peticiones[1]['request']->getBody(), true);
    $ruta = tempnam(sys_get_temp_dir(), 'test').'.zip';
    file_put_contents($ruta, base64_decode($cuerpo['archivo']['arcGreZip']));

    $zip = new ZipArchive;
    $zip->open($ruta);

    expect($zip->getNameIndex(0))->toBe('20364000643-31-V001-9.xml');

    $zip->close();
    unlink($ruta);
});

it('reads an accepted CDR', function (): void {
    $enviador = enviadorConRespuestas([
        respuestaJson(['access_token' => 'token-abc']),
        respuestaJson(['codRespuesta' => '0', 'arcCdr' => 'UEsDBB==', 'indCdrGenerado' => '1']),
    ]);

    expect($enviador->consultar('202609061234567'))
        ->aceptada->toBeTrue()
        ->cdr->toBe('UEsDBB==');
});

it('surfaces the rejection detail, which is the only way to fix the guide', function (): void {
    $enviador = enviadorConRespuestas([
        respuestaJson(['access_token' => 'token-abc']),
        respuestaJson([
            'codRespuesta' => '99',
            'error' => ['numError' => '2325', 'desError' => 'El dato ingresado en TUC del vehiculo no cumple con el formato establecido'],
        ]),
    ]);

    expect($enviador->consultar('202609061234567'))
        ->aceptada->toBeFalse()
        ->codigo->toBe('2325')
        ->mensaje->toContain('TUC del vehiculo');
});

it('refuses to send without SOL credentials instead of failing at SUNAT', function (): void {
    config()->set('gre.api.client_secret', null);

    enviadorConRespuestas([])->enviar('20364000643-31-V001-1', '<root/>');
})->throws(RuntimeException::class, 'Falta la credencial gre.api.client_secret');
