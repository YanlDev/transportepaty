<?php

use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\ConstructorGuiaTransportista;
use App\Services\FirmadorGuiaTransportista;

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

/**
 * El XML como árbol consultable, para afirmar sobre nodos y no sobre texto.
 */
function xmlDe(Viaje $viaje, string $correlativo = '1'): DOMXPath
{
    $guia = app(ConstructorGuiaTransportista::class)->construir($viaje, $correlativo);
    $xml = app(FirmadorGuiaTransportista::class)->generar($guia);

    $documento = new DOMDocument;
    $documento->loadXML($xml);

    return new DOMXPath($documento);
}

function valorEn(DOMXPath $xpath, string $consulta): ?string
{
    $nodos = $xpath->query($consulta);

    return $nodos !== false && $nodos->length > 0 ? trim($nodos->item(0)->textContent) : null;
}

it('lists everything missing instead of failing on the first gap', function (): void {
    $viaje = Viaje::factory()->create([
        'cliente_ruc' => null,
        'destinatario_ruc' => null,
    ]);

    $faltantes = app(ConstructorGuiaTransportista::class)->faltantes($viaje);

    expect($faltantes)->toContain('Falta el punto de partida (la GRE exige su ubigeo)')
        ->toContain('Falta el punto de llegada (la GRE exige su ubigeo)')
        ->toContain('Falta el RUC del destinatario')
        ->toContain('Falta el RUC del remitente')
        ->toContain('El viaje no está vinculado a un conductor del padrón')
        ->toContain('El viaje no está vinculado a un tracto del padrón');
});

it('reports a tracto without TUC, since SUNAT demands it per plate', function (): void {
    $viaje = Viaje::factory()->emisible()->create([
        'tracto_id' => Vehiculo::factory()->create(['placa' => 'VCX787', 'tuc' => null]),
    ]);

    expect(app(ConstructorGuiaTransportista::class)->faltantes($viaje))
        ->toContain('El tracto VCX787 no tiene TUC registrado');
});

it('reports a conductor without licencia', function (): void {
    $viaje = Viaje::factory()->emisible()->create([
        'conductor_id' => Conductor::factory()->create(['nombres' => 'ROSENDO', 'licencia' => null]),
    ]);

    expect(app(ConstructorGuiaTransportista::class)->faltantes($viaje))
        ->toContain('El conductor ROSENDO no tiene licencia registrada');
});

it('considers a complete viaje ready to emit', function (): void {
    $viaje = Viaje::factory()->emisible()->create();

    expect(app(ConstructorGuiaTransportista::class)->faltantes($viaje))->toBe([]);
});

it('refuses to build a document when data is missing', function (): void {
    $viaje = Viaje::factory()->create();

    app(ConstructorGuiaTransportista::class)->construir($viaje, '1');
})->throws(RuntimeException::class, 'no se puede emitir');

it('builds a transportista guide, not a remitente one', function (): void {
    $xpath = xmlDe(Viaje::factory()->emisible()->create());

    expect(valorEn($xpath, '//cbc:DespatchAdviceTypeCode'))->toBe('31');
});

it('names the document with the emisor RUC, type and series', function (): void {
    $viaje = Viaje::factory()->emisible()->create();

    $guia = app(ConstructorGuiaTransportista::class)->construir($viaje, '7');

    expect(app(FirmadorGuiaTransportista::class)->nombreArchivo($guia))
        ->toBe('20364000643-31-V001-7');
});

it('puts the carrier as emisor and the cargo owner as remitente', function (): void {
    $xpath = xmlDe(Viaje::factory()->emisible()->create());

    expect(valorEn($xpath, '//cac:DespatchSupplierParty//cbc:ID'))->toBe('20364000643')
        ->and(valorEn($xpath, '//cac:DeliveryCustomerParty//cbc:ID'))->toBe('20100136741')
        ->and(valorEn($xpath, '//cac:SellerSupplierParty//cbc:ID'))->toBe('20100136741');
});

it('carries a single generic cargo line, since the detail lives in the remitente guide', function (): void {
    $viaje = Viaje::factory()->emisible()->create(['tipo_carga' => TipoCarga::Concentrado]);
    $xpath = xmlDe($viaje);

    // El XSD exige al menos una línea aunque la representación impresa no la
    // muestre: el sandbox de SUNAT rechaza el documento sin ella con el error
    // 0306 «Missing child element(s). Expected is DespatchLine».
    expect($xpath->query('//cac:DespatchLine')->length)->toBe(1)
        ->and(valorEn($xpath, '//cac:DespatchLine//cbc:Description'))->toBe('Concentrado')
        ->and(valorEn($xpath, '//cac:AdditionalDocumentReference/cbc:ID'))->toBe('T012-855')
        ->and(valorEn($xpath, '//cbc:GrossWeightMeasure'))->toBe('10150.000');
});

it('declares the ubigeo of both points', function (): void {
    $xpath = xmlDe(Viaje::factory()->emisible()->create());

    expect(valorEn($xpath, '//cac:DespatchAddress/cbc:ID'))->toBe('070101')
        ->and(valorEn($xpath, '//cac:DeliveryAddress/cbc:ID'))->toBe('210902');
});

it('declares tracto, carreta, their TUC and the company MTC number', function (): void {
    $xpath = xmlDe(Viaje::factory()->emisible()->create());

    expect(valorEn($xpath, '//cac:TransportEquipment/cbc:ID'))->toBe('VEP793')
        ->and(valorEn($xpath, '//cac:TransportEquipment//cbc:RegistrationNationalityID'))->toBe('21M25000279E')
        ->and(valorEn($xpath, '//cac:AttachedTransportEquipment/cbc:ID'))->toBe('BRI984');
});

it('declares the driver licencia', function (): void {
    $xpath = xmlDe(Viaje::factory()->emisible()->create());

    expect(valorEn($xpath, '//cac:DriverPerson/cbc:ID'))->toBe('01328149');
});

it('adds the establecimiento code only when the point has both RUC and code', function (): void {
    $viaje = Viaje::factory()->emisible()->create();
    $viaje->puntoLlegada->update(['ruc' => '20100136741', 'cod_local' => '0007']);

    $xpath = xmlDe($viaje->fresh());

    expect(valorEn($xpath, '//cac:DeliveryAddress/cbc:AddressTypeCode'))->toBe('0007');
});
