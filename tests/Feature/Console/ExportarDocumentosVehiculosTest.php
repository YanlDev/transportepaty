<?php

use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');

    $this->destino = storage_path('framework/testing/exportacion-'.uniqid());
    File::makeDirectory($this->destino, 0775, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->destino);
});

/**
 * Un PDF mínimo pero real: la colección de media valida el mime por contenido.
 */
function pdfDePrueba(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'documento.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
    );
}

function documentoConArchivo(Vehiculo $vehiculo, TipoDocumento $tipo, ?string $vencimiento = null): VehiculoDocumento
{
    $documento = $vehiculo->documentos()->create([
        'tipo' => $tipo,
        'fecha_vencimiento' => $vencimiento,
    ]);

    $documento->addMedia(pdfDePrueba())->toMediaCollection('archivo');

    return $documento;
}

it('writes one folder per placa with the document files inside', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VBH-902', 'tipo' => TipoVehiculo::Tracto]);
    $carreta = Vehiculo::factory()->create(['placa' => 'BUJ-810', 'tipo' => TipoVehiculo::Carreta]);

    documentoConArchivo($tracto, TipoDocumento::Soat, '2027-02-15');
    documentoConArchivo($tracto, TipoDocumento::TarjetaPropiedad);
    documentoConArchivo($carreta, TipoDocumento::Matpel, '2026-11-25');

    $this->artisan('transpaty:exportar-documentos-vehiculos', ['ruta' => $this->destino])
        ->assertSuccessful();

    $vehiculos = $this->destino.'/VEHICULOS';

    expect($vehiculos.'/VBH-902/soat_VBH-902_vence-2027-02-15.pdf')->toBeFile()
        ->and($vehiculos.'/VBH-902/tarjeta_propiedad_VBH-902.pdf')->toBeFile()
        ->and($vehiculos.'/BUJ-810/matpel_BUJ-810_vence-2026-11-25.pdf')->toBeFile();
});

it('writes nothing in dry-run mode', function (): void {
    $vehiculo = Vehiculo::factory()->create(['placa' => 'VBH-902']);
    documentoConArchivo($vehiculo, TipoDocumento::Soat);

    $this->artisan('transpaty:exportar-documentos-vehiculos', [
        'ruta' => $this->destino,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(File::exists($this->destino.'/VEHICULOS'))->toBeFalse();
});

/**
 * Volver a pasar la carpeta no debería reescribir los PDF ya exportados, para
 * que OneDrive no vuelva a sincronizar cientos de archivos idénticos.
 */
it('skips files already present unless forced', function (): void {
    $vehiculo = Vehiculo::factory()->create(['placa' => 'VBH-902']);
    documentoConArchivo($vehiculo, TipoDocumento::Soat);

    $this->artisan('transpaty:exportar-documentos-vehiculos', ['ruta' => $this->destino])->assertSuccessful();

    $archivo = $this->destino.'/VEHICULOS/VBH-902/soat_VBH-902.pdf';
    File::put($archivo, 'editado a mano');

    $this->artisan('transpaty:exportar-documentos-vehiculos', ['ruta' => $this->destino])->assertSuccessful();
    expect(File::get($archivo))->toBe('editado a mano');

    $this->artisan('transpaty:exportar-documentos-vehiculos', [
        'ruta' => $this->destino,
        '--forzar' => true,
    ])->assertSuccessful();
    expect(File::get($archivo))->not->toBe('editado a mano');
});

it('reports a document with no file instead of failing', function (): void {
    $vehiculo = Vehiculo::factory()->create(['placa' => 'VBH-902']);
    $vehiculo->documentos()->create(['tipo' => TipoDocumento::Soat]);

    $this->artisan('transpaty:exportar-documentos-vehiculos', ['ruta' => $this->destino])
        ->expectsOutputToContain('sin archivo cargado')
        ->assertSuccessful();

    expect(File::exists($this->destino.'/VEHICULOS/VBH-902'))->toBeFalse();
});

it('fails when the given folder does not exist', function (): void {
    $this->artisan('transpaty:exportar-documentos-vehiculos', ['ruta' => $this->destino.'/fantasma'])
        ->assertFailed();
});

it('names the root folder as asked and groups the placas by tipo', function (): void {
    $tracto = Vehiculo::factory()->create(['placa' => 'VBH-902', 'tipo' => TipoVehiculo::Tracto]);
    $carreta = Vehiculo::factory()->create(['placa' => 'BUJ-810', 'tipo' => TipoVehiculo::Carreta]);

    documentoConArchivo($tracto, TipoDocumento::Soat, '2027-02-15');
    documentoConArchivo($carreta, TipoDocumento::Matpel, '2026-11-25');

    $this->artisan('transpaty:exportar-documentos-vehiculos', [
        'ruta' => $this->destino,
        '--carpeta' => 'Flota Paty',
        '--por-tipo' => true,
    ])->assertSuccessful();

    $flota = $this->destino.'/Flota Paty';

    expect($flota.'/TRACTO/VBH-902/soat_VBH-902_vence-2027-02-15.pdf')->toBeFile()
        ->and($flota.'/CARRETA/BUJ-810/matpel_BUJ-810_vence-2026-11-25.pdf')->toBeFile()
        ->and(File::exists($flota.'/VBH-902'))->toBeFalse();
});

it('refuses a folder name that escapes the given ruta', function (): void {
    $this->artisan('transpaty:exportar-documentos-vehiculos', [
        'ruta' => $this->destino,
        '--carpeta' => '../fuera',
    ])->assertFailed();
});
