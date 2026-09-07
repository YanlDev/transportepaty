<?php

use App\Models\Viaje;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('gre.emisor', [
        'ruc' => '20364000643',
        'razon_social' => 'EMPRESA DE TRANSPORTES PATY S.C.R.L.',
        'nombre_comercial' => 'TRANSPORTES PATY',
        'direccion' => 'MZ. C4 LOTE. 6Y7 LOTIZACION INDUSTRIAL HUACHIPA',
        'ubigeo' => '150118',
        'registro_mtc' => '210122CNG',
    ]);
    config()->set('gre.serie', 'V001');
});

it('fails and lists what is missing', function (): void {
    $viaje = Viaje::factory()->create(['numero_gr' => 'EG03-00000001']);

    $this->artisan('gre:probar', ['viaje' => 'EG03-00000001'])
        ->expectsOutputToContain('Falta el punto de partida')
        ->expectsOutputToContain('no está vinculado a un tracto')
        ->assertExitCode(1);

    expect($viaje->fresh()->gre_ticket)->toBeNull();
});

it('generates a well formed XML for a complete viaje', function (): void {
    Viaje::factory()->emisible()->create(['numero_gr' => 'EG03-00012342']);

    $this->artisan('gre:probar', ['viaje' => 'EG03-00012342'])
        ->expectsOutputToContain('XML generado y bien formado')
        ->expectsOutputToContain('20364000643-31-V001-1.xml')
        ->expectsOutputToContain('No se envió nada a SUNAT')
        ->assertExitCode(0);
});

it('writes the XML to disk when asked', function (): void {
    Viaje::factory()->emisible()->create(['numero_gr' => 'EG03-00012343']);
    $destino = sys_get_temp_dir().'/gret-'.uniqid().'.xml';

    $this->artisan('gre:probar', ['viaje' => 'EG03-00012343', '--guardar' => $destino])
        ->assertExitCode(0);

    expect(file_get_contents($destino))->toContain('<cbc:DespatchAdviceTypeCode');

    unlink($destino);
});

it('reports when the viaje does not exist', function (): void {
    $this->artisan('gre:probar', ['viaje' => 'EG03-99999999'])
        ->expectsOutputToContain('No se encontró el viaje')
        ->assertExitCode(1);
});

it('explains that signing needs a certificate that is not installed', function (): void {
    // Disco falso: si no, la prueba pasa o falla según haya un certificado
    // instalado en la máquina donde corra.
    Storage::fake('local');

    Viaje::factory()->emisible()->create(['numero_gr' => 'EG03-00012344']);

    $this->artisan('gre:probar', ['viaje' => 'EG03-00012344', '--firmar' => true])
        ->expectsOutputToContain('No se encuentra el certificado digital')
        ->assertExitCode(1);
});
