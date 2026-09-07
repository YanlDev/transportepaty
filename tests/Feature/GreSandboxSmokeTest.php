<?php

use App\Models\Viaje;
use App\Services\ConstructorGuiaTransportista;
use App\Services\EnviadorGuiaSunat;
use App\Services\FirmadorGuiaTransportista;
use Illuminate\Support\Facades\Storage;

/**
 * Prueba de punta a punta contra el sandbox público del API GRE: firma con el
 * certificado demo, envía y lee la respuesta.
 *
 * Sale a la red, así que no corre sola. Para ejecutarla:
 *
 *   GRE_SANDBOX=1 php artisan test --filter=GreSandboxSmokeTest
 *
 * Qué se puede afirmar acá y qué no: el sandbox valida el XML contra el
 * esquema UBL y ahí sí da un veredicto real —fue el que reveló que la GRE-T
 * necesita una línea de bienes aunque no la imprima—. Lo que no puede hacer es
 * aceptar el documento: su contribuyente de prueba no está registrado como
 * empresa de transporte, así que todo tipo 31 muere con 3383 sin importar el
 * contenido (un tipo 09 con los mismos datos falla con otro error, propio del
 * contenido). La aceptación completa solo se confirma en Beta de SUNAT con el
 * RUC y el certificado de la empresa.
 */
beforeEach(function (): void {
    if (! env('GRE_SANDBOX')) {
        $this->markTestSkipped('Prueba de red: requiere GRE_SANDBOX=1.');
    }

    if (! Storage::disk('local')->exists('gre/certificado-demo.pem')) {
        $this->markTestSkipped('Falta el certificado demo en storage/app/private/gre/certificado-demo.pem.');
    }

    // Credenciales y emisor del demo oficial de Greenter. El certificado demo
    // es público y no sirve para emitir nada con valor tributario.
    config()->set('gre.emisor', [
        'ruc' => '20161515648',
        'razon_social' => 'GREENTER S.A.C.',
        'nombre_comercial' => 'GREENTER',
        'direccion' => 'AV. LIMA 123',
        'ubigeo' => '150101',
        'registro_mtc' => '210122CNG',
    ]);
    config()->set('gre.serie', 'V001');
    config()->set('gre.certificado', 'gre/certificado-demo.pem');
    config()->set('gre.api', [
        'client_id' => 'test-85e5b0ae-255c-4891-a595-0b98c65c9854',
        'client_secret' => 'test-Hty/M6QshYvPgItX2P0+Kw==',
        'usuario_sol' => 'MODDATOS',
        'clave_sol' => 'MODDATOS',
    ]);
    config()->set('gre.endpoints', [
        'auth' => 'https://gre-test.nubefact.com/v1',
        'cpe' => 'https://gre-test.nubefact.com/v1',
    ]);
});

it('produces an XML that passes SUNAT schema validation', function (): void {
    // SUNAT rechaza con 2108 «Presentación fuera de fecha» los documentos con
    // emisión antigua, así que la guía de prueba se emite hoy.
    $viaje = Viaje::factory()->emisible()->create([
        'fecha_emision' => now(),
        'fecha_traslado' => today(),
    ]);

    $guia = app(ConstructorGuiaTransportista::class)->construir($viaje, (string) random_int(1, 99999));
    $firmador = app(FirmadorGuiaTransportista::class);
    $xml = $firmador->firmar($guia);

    $enviador = app(EnviadorGuiaSunat::class);
    $ticket = $enviador->enviar($firmador->nombreArchivo($guia), $xml);

    expect($ticket)->not->toBeEmpty();

    $resultado = $enviador->consultar($ticket);

    // El documento llegó a la validación de negocio, que es lo más lejos que
    // este ambiente permite. Si volviera a aparecer un error de esquema —0306
    // o cualquier ExceptionXsd— significa que el XML dejó de ser válido.
    expect($resultado['codigo'])->not->toBe('0306')
        ->and($resultado['mensaje'] ?? '')->not->toContain('ExceptionXsd')
        ->and($resultado['mensaje'] ?? '')->not->toContain('DespatchLine');
});
