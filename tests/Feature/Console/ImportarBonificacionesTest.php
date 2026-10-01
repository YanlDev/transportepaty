<?php

use App\Enums\TipoDocumento;
use App\Models\Vehiculo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Escribe un PDF con los mismos fragmentos de texto que trae una resolución
 * real de PROVIAS: número de RSD, fecha, placa y número de permiso.
 */
function resolucionDePrueba(string $carpeta, string $archivo, string $placa, string $permiso, string $rsd): void
{
    Pdf::loadHTML("<p>Resolución Subdirectoral N° {$rsd}–2026–MTC/20.13.2</p>
        <p>Lima, 24 de febrero del 2026</p>
        <p>ARTÍCULO PRIMERO.- Otorgar el Permiso de Bonificación N° {$permiso} para el vehículo
        con Placa Única Nacional de Rodaje N° {$placa} perteneciente a la empresa</p>")
        ->save("{$carpeta}/{$archivo}");
}

beforeEach(function (): void {
    Storage::fake('public');
    $this->carpeta = sys_get_temp_dir().'/bonificaciones-'.uniqid();
    File::makeDirectory($this->carpeta);
});

afterEach(function (): void {
    File::deleteDirectory($this->carpeta);
});

it('adjunta la bonificación según la placa del texto y no según el nombre del archivo', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'BWD-997']);
    // El nombre repite el número de otra resolución, como pasa en la web de PROVIAS.
    resolucionDePrueba($this->carpeta, 'E-004732 RSD 04571.pdf', 'BWD-997', 'PBM2602953', '004606');

    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta])->assertSuccessful();

    $documento = $tracto->documentoDe(TipoDocumento::Bonificacion);

    expect($documento)->not->toBeNull()
        ->and($documento->numero)->toBe('PBM2602953')
        ->and($documento->nombre)->toBe('RSD N° 004606-2026-MTC/20.13.2')
        ->and($documento->fecha_emision->format('Y-m-d'))->toBe('2026-02-24')
        ->and($documento->fecha_vencimiento)->toBeNull()
        ->and($documento->observaciones)->toContain('E-004732%20RSD%2004571.pdf')
        ->and($documento->getFirstMedia('archivo'))->not->toBeNull();
});

it('encuentra el vehículo aunque su placa esté cargada sin guion', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VGB985']);
    resolucionDePrueba($this->carpeta, 'E-004732 RSD 04594.pdf', 'VGB-985', 'PBM2602941', '004594');

    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta])->assertSuccessful();

    expect($carreta->documentoDe(TipoDocumento::Bonificacion)?->numero)->toBe('PBM2602941');
});

it('se queda con el permiso más reciente cuando una placa tiene dos', function (): void {
    $carreta = Vehiculo::factory()->carreta()->create(['placa' => 'VFM-985']);
    resolucionDePrueba($this->carpeta, 'E-004732 RSD 04590.pdf', 'VFM-985', 'PBM2602937', '004590');
    resolucionDePrueba($this->carpeta, 'E-004732 RSD 04600.pdf', 'VFM-985', 'PBM2602947', '004600');

    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta])->assertSuccessful();

    expect($carreta->documentos()->where('tipo', TipoDocumento::Bonificacion)->count())->toBe(1)
        ->and($carreta->documentoDe(TipoDocumento::Bonificacion)->numero)->toBe('PBM2602947');
});

it('no vuelve a cargar lo que ya está y no escribe nada en seco', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'BWD-997']);
    resolucionDePrueba($this->carpeta, 'E-004732 RSD 04606.pdf', 'BWD-997', 'PBM2602953', '004606');

    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta, '--dry-run' => true])
        ->expectsOutputToContain('[dry-run] Cargados: 1')
        ->assertSuccessful();

    expect($tracto->documentoDe(TipoDocumento::Bonificacion))->toBeNull();

    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta])->assertSuccessful();
    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta])
        ->expectsOutputToContain('Cargados: 0 · ya estaban: 1')
        ->assertSuccessful();

    expect($tracto->fresh()->documentoDe(TipoDocumento::Bonificacion)->getMedia('archivo'))->toHaveCount(1);
});

it('avisa las placas que no están en la flota y las que se quedan sin bonificación', function (): void {
    Vehiculo::factory()->create(['placa' => 'TCD-879']);
    resolucionDePrueba($this->carpeta, 'E-004799 RSD 04608.pdf', 'VJN-972', 'PBM2602955', '004608');

    $this->artisan('transpaty:importar-bonificaciones', ['ruta' => $this->carpeta])
        ->expectsOutputToContain('VJN-972: tiene bonificación (PBM2602955) pero no está en la flota')
        ->expectsOutputToContain('TCD-879 (Tracto)')
        ->assertSuccessful();
});
