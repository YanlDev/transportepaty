<?php

use App\Services\LectorGuiaRemision;

/**
 * Fixtures reales: representaciones impresas genuinas de GRE de SUNAT,
 * copiadas de las carpetas del cliente. Cubren los formatos que rompían al
 * primer intento contra el corpus completo (73 archivos, ver historial):
 * varias guías de remisión remitente en un solo documento, peso sin parte
 * decimal, y el sufijo «- FÍSICO» en la etiqueta de guía remitente.
 */
it('extracts every field from a standard GRE', function (): void {
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-minsur-concentrado.pdf'),
    );

    expect($campos)->toMatchArray([
        'numero_gr' => 'EG03-00011965',
        'fecha_emision' => '30/07/2026 09:31 AM',
        'fecha_traslado' => '30/07/2026',
        'cliente' => 'MINSUR S.A.',
        'cliente_ruc' => '20100136741',
        'destinatario' => 'MINSUR S.A.',
        'destinatario_ruc' => '20100136741',
        'peso' => '30.045',
        'unidad_peso' => 'TNE',
        'placa_tracto' => 'CAM703',
        'placa_carreta' => 'VGK987',
        'conductor_nombre' => 'PANDURO AMARO ADAN RONAL',
        'conductor_dni' => '42466432',
    ]);

    expect($campos['origen'])->toContain('SAN RAFAEL');
    expect($campos['destino'])->toContain('PISCO');
    expect($campos['guias_remitente'])->toBe([
        ['numero' => 'T007 - 9590', 'ruc' => '20100136741'],
    ]);
});

it('collects more than one guía de remisión remitente when the document references several', function (): void {
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-san-lorenzo-multi-guia.pdf'),
    );

    expect($campos['guias_remitente'])->toBe([
        ['numero' => 'T003 - 164953', 'ruc' => '20307146798'],
        ['numero' => 'T001 - 243466', 'ruc' => '20307146798'],
    ]);
    // Cliente (remitente) y destinatario son empresas distintas en este caso.
    expect($campos['cliente'])->toBe('Ceramica San Lorenzo S.A.C.');
    expect($campos['destinatario'])->toBe('DISERGO SAC');
    // Trae coma de miles: «13,791.526».
    expect($campos['peso'])->toBe('13,791.526');
    expect($campos['unidad_peso'])->toBe('KGM');
});

it('reads a weight with no decimal part', function (): void {
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-minsur-ransa-peso-sin-decimales.pdf'),
    );

    expect($campos['peso'])->toBe('5,464');
});

it('recognises the "- FÍSICO" suffix on a referenced guía remitente', function (): void {
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-crisar-aje-guia-fisica.pdf'),
    );

    expect($campos['guias_remitente'])->toBe([
        ['numero' => '0008 - 1729', 'ruc' => '20605908811'],
    ]);
});

it('extracts the subcontratador when the freight is paid by one', function (): void {
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-ajeper-subcontratado-crisar.pdf'),
    );

    // El remitente sigue siendo Ajeper: quien decide si el subcontratador
    // reemplaza al cliente es `ImportadorViaje`, no este lector.
    expect($campos['cliente'])->toBe('AJEPER S.A.');
    expect($campos['subcontratador'])->toBe('CRISAR LOGISTICA S.A.C.');
    expect($campos['subcontratador_ruc'])->toBe('20603930844');
});

it('returns a null subcontratador when the freight has no subcontractor', function (): void {
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-minsur-concentrado.pdf'),
    );

    expect($campos['subcontratador'])->toBeNull();
    expect($campos['subcontratador_ruc'])->toBeNull();
});

it('extracts the destinatario when its RUC label wraps onto the next line', function (): void {
    // Cuando remitente y destinatario son la misma empresa (transporte propio
    // de combustible), la etiqueta «REGISTRO ÚNICO DE CONTRIBUYENTES N°» del
    // destinatario a veces se corta justo antes del «N°», con el número en la
    // línea siguiente — a diferencia del remitente, donde el corte cae después.
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-combustible-destinatario-partido.pdf'),
    );

    expect($campos['destinatario'])->toBe('SERVICENTRO PANAMERICANO SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA');
    expect($campos['destinatario_ruc'])->toBe('20448484816');
});

it('extracts every field from a GRE Remitente, not just the GRE Transportista that Paty issues', function (): void {
    // A diferencia de la GRE Transportista, acá quien emite el documento es
    // el dueño de la carga (no hay sección «Datos del remitente:»): el
    // cliente real sale de la razón social que encabeza el PDF.
    $campos = (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/guias/gr-melma-remitente.pdf'),
    );

    expect($campos)->toMatchArray([
        'numero_gr' => 'EG07-00000193',
        'fecha_emision' => '05/08/2026 03:01 PM',
        'fecha_traslado' => '05/08/2026',
        'cliente' => 'IMPORTACIONES MELMA S.A.C.',
        'cliente_ruc' => '20600812913',
        'destinatario' => 'IMPORTACIONES MELMA S.A.C.',
        'destinatario_ruc' => '20600812913',
        'peso' => '32,500',
        'unidad_peso' => 'KGM',
        'placa_tracto' => 'BJI770',
        'placa_carreta' => 'VFK972',
        'conductor_nombre' => 'PINEDA TITO LUCIO',
        'conductor_dni' => '02411719',
    ]);

    expect($campos['origen'])->toBe('MZ. 02 Z.I. BALNEARIOS DEL SUR LOTE 3, 4, 5, 6 SECTOR D - ILO - ILO - MOQUEGUA');
    expect($campos['destino'])->toBe('JR. MARIANO MELGAR NRO. 706 TUPAC AMARU - JULIACA - SAN ROMAN - PUNO');
});

it('throws when the file is not a parseable PDF', function (): void {
    // El lector no atrapa esto a propósito: es `ImportadorViaje` quien decide
    // qué hacer con un archivo que no se puede leer (saltarlo sin tumbar el
    // resto del lote), no este servicio.
    (new LectorGuiaRemision)->extraerDesdeArchivo(
        base_path('tests/Fixtures/disponibilidad-real.xlsx'),
    );
})->throws(Exception::class);
