<?php

use App\Models\Ajuste;
use App\Models\AreaAviso;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\Programacion;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\AvisoDeSalida;

/**
 * El texto del preaviso es la constancia de lo que se le dijo al conductor,
 * así que lo que importa acá es que diga lo que tiene que decir: la unidad, la
 * prohibición y lo que cuesta romperla.
 */
it('names the unit, the trip and the prohibition in the aviso', function (): void {
    $programacion = Programacion::factory()->create([
        'fecha' => '2026-09-22',
        'vehiculo_id' => Vehiculo::factory()->create(['placa' => 'VEP-793'])->id,
        'conductor_id' => Conductor::factory()->create(['nombres' => 'Juan', 'apellidos' => 'Pérez'])->id,
        'cliente_id' => Cliente::factory()->create(['alias' => 'MINSUR'])->id,
        'destino' => 'JULIACA',
    ]);

    $mensaje = app(AvisoDeSalida::class)->mensajeParaConductor($programacion->fresh());

    expect($mensaje)
        ->toContain('VEP-793')
        ->toContain('Juan Pérez')
        ->toContain('MINSUR')
        ->toContain('JULIACA')
        ->toContain('22/09/2026')
        ->toContain('No inicies el viaje sin documentación validada')
        ->not->toContain('CONFIRMO');
});

it('puts the oficina phone in the advertencia when it is configured', function (): void {
    Ajuste::guardar(Ajuste::TELEFONO_OFICINA, '998 877 665');

    $aviso = app(AvisoDeSalida::class);

    // Solo en la advertencia: el aviso de programación se deja corto.
    expect($aviso->advertencia())->toContain('Oficina: 998 877 665')
        ->and($aviso->mensajeParaConductor(Programacion::factory()->create()))
        ->not->toContain('Oficina:');
});

it('leaves out the oficina line when there is no phone configured', function (): void {
    Ajuste::guardar(Ajuste::TELEFONO_OFICINA, null);

    expect(app(AvisoDeSalida::class)->advertencia())->not->toContain('Oficina:');
});

/**
 * La advertencia va como mensaje aparte del aviso de programación: pegada al
 * otro quedaría escondida detrás del «ver más» de WhatsApp, que es justo lo
 * que no puede pasarle.
 */
it('spells out in the advertencia which documents are needed and who pays the multa', function (): void {
    $advertencia = app(AvisoDeSalida::class)->advertencia();

    expect($advertencia)
        ->toContain('PROHIBIDO INICIAR EL VIAJE SIN DOCUMENTACIÓN VALIDADA')
        ->toContain('Guía de Remisión del Remitente (GR)')
        ->toContain('Guía de Remisión del Transportista (GRT)')
        ->toContain('excepción normativa')
        ->toContain('4 UIT')
        ->toContain('el conductor se hará cargo de pagar la multa');
});

it('keeps the aviso short, without the whole advertencia inside', function (): void {
    $mensaje = app(AvisoDeSalida::class)->mensajeParaConductor(Programacion::factory()->create());

    expect($mensaje)->not->toContain('Guía de Remisión del Transportista (GRT)');
});

/**
 * Los celulares se guardan con los nueve dígitos de siempre; WhatsApp los pide
 * con el código de país y sin separadores.
 */
it('puts the country code on a peruvian celular', function (): void {
    $aviso = app(AvisoDeSalida::class);

    expect($aviso->numeroWhatsapp('963325022'))->toBe('51963325022')
        ->and($aviso->numeroWhatsapp('963 325 022'))->toBe('51963325022')
        ->and($aviso->numeroWhatsapp('51963325022'))->toBe('51963325022');
});

it('gives no number when there is nothing to dial', function (): void {
    $aviso = app(AvisoDeSalida::class);

    expect($aviso->numeroWhatsapp(null))->toBeNull()
        ->and($aviso->numeroWhatsapp(''))->toBeNull()
        ->and($aviso->numeroWhatsapp('12345'))->toBeNull();
});

/**
 * El resumen que mira quien no está en el patio: lo que importa es cuántas
 * unidades siguen sin poder salir.
 */
it('marks in the day summary which units still have no GR', function (): void {
    $conGr = Programacion::factory()->create([
        'vehiculo_id' => Vehiculo::factory()->create(['placa' => 'VEP-793'])->id,
    ]);
    $sinGr = Programacion::factory()->create([
        'vehiculo_id' => Vehiculo::factory()->create(['placa' => 'BUK-886'])->id,
    ]);

    $resumen = app(AvisoDeSalida::class)->resumenDelDia(
        '2026-09-22',
        collect([$conGr, $sinGr]),
        [$conGr->vehiculo_id => 'EG03-00012489'],
    );

    expect($resumen)
        ->toContain('EG03-00012489')
        ->toContain('SIN GR')
        ->toContain('BUK-886')
        ->toContain('1 unidad sigue sin GR');
});

it('says so when every unit of the day has its GR', function (): void {
    $programacion = Programacion::factory()->create();

    $resumen = app(AvisoDeSalida::class)->resumenDelDia(
        '2026-09-22',
        collect([$programacion]),
        [$programacion->vehiculo_id => 'EG03-00012489'],
    );

    expect($resumen)->toContain('Todas las unidades programadas tienen su GR.');
});

/**
 * A quién se le puede mandar el aviso: el celular del conductor, el otro que
 * usa —que está en su ficha— y el adicional de esa salida.
 */
it('offers every whatsapp where the aviso can go', function (): void {
    $programacion = Programacion::factory()->create([
        'conductor_id' => Conductor::factory()->create([
            'telefono' => '963325022',
            'telefono_alterno' => '951112233',
        ])->id,
        'whatsapp_adicional' => '944556677',
    ]);

    $destinatarios = app(AvisoDeSalida::class)->destinatarios($programacion->fresh());

    expect($destinatarios)->toBe([
        ['etiqueta' => 'Conductor', 'numero' => '51963325022'],
        ['etiqueta' => 'Alterno', 'numero' => '51951112233'],
        ['etiqueta' => 'Adicional', 'numero' => '51944556677'],
    ]);
});

it('does not offer the same chat twice', function (): void {
    $programacion = Programacion::factory()->create([
        'conductor_id' => Conductor::factory()->create([
            'telefono' => '963325022',
            'telefono_alterno' => '963 325 022',
        ])->id,
        'whatsapp_adicional' => null,
    ]);

    expect(app(AvisoDeSalida::class)->destinatarios($programacion->fresh()))
        ->toBe([['etiqueta' => 'Conductor', 'numero' => '51963325022']]);
});

it('leaves the destinatarios empty when there is no number anywhere', function (): void {
    $programacion = Programacion::factory()->create([
        'conductor_id' => Conductor::factory()->create([
            'telefono' => null,
            'telefono_alterno' => null,
        ])->id,
        'whatsapp_adicional' => null,
    ]);

    expect(app(AvisoDeSalida::class)->destinatarios($programacion->fresh()))->toBe([]);
});

/**
 * Abastecimiento prepara la carga: le basta qué unidad sale, con quién y a
 * dónde. Los montos no le corresponden y no viajan a su WhatsApp.
 */
it('tells abastecimiento the unit, the conductor and the destino, without money', function (): void {
    $programacion = Programacion::factory()->create([
        'fecha' => '2026-09-25',
        'vehiculo_id' => Vehiculo::factory()->create(['placa' => 'VEP-897'])->id,
        'conductor_id' => Conductor::factory()->create(['nombres' => 'Juan', 'apellidos' => 'Pérez'])->id,
        'destino' => 'JULIACA',
        'precio_flete' => 1850,
    ]);

    $mensaje = app(AvisoDeSalida::class)->mensajeParaArea($programacion->fresh(), AreaAviso::factory()->make());

    expect($mensaje)
        ->toContain('VEP-897')
        ->toContain('Juan Pérez')
        ->toContain('JULIACA')
        ->toContain('25/09/2026')
        ->not->toContain('1,850')
        ->not->toContain('Flete');
});

it('gives facturacion the cliente and the flete acordado', function (): void {
    $programacion = Programacion::factory()->create([
        'cliente_id' => Cliente::factory()->create(['alias' => 'MINSUR'])->id,
        'precio_flete' => 1850,
        'precio_incluye_igv' => false,
    ]);

    expect(app(AvisoDeSalida::class)->mensajeParaArea($programacion->fresh(), AreaAviso::factory()->veFlete()->make()))
        ->toContain('MINSUR')
        ->toContain('Flete acordado: *S/ 1,850.00* + IGV')
        ->toContain('Total con IGV: S/ 2,183.00');
});

/**
 * Los dos importes en el mensaje: facturación necesita el neto y el total, y
 * no tiene por qué sacar la cuenta cada vez.
 */
it('shows the neto when the flete was agreed with IGV inside', function (): void {
    $programacion = Programacion::factory()->create([
        'precio_flete' => 2183,
        'precio_incluye_igv' => true,
    ]);

    expect(app(AvisoDeSalida::class)->mensajeParaArea($programacion->fresh(), AreaAviso::factory()->veFlete()->make()))
        ->toContain('Flete acordado: *S/ 2,183.00* (IGV incluido)')
        ->toContain('Neto: S/ 1,850.00');
});

it('breaks down the flete both ways', function (): void {
    $sinIgv = Programacion::factory()->create(['precio_flete' => 1850, 'precio_incluye_igv' => false]);
    $conIgv = Programacion::factory()->create(['precio_flete' => 2183, 'precio_incluye_igv' => true]);

    $aviso = app(AvisoDeSalida::class);

    expect($aviso->desglosarFlete($sinIgv->fresh()))->toBe(['neto' => 1850.0, 'total' => 2183.0])
        ->and($aviso->desglosarFlete($conIgv->fresh()))->toBe(['neto' => 1850.0, 'total' => 2183.0]);
});

/**
 * Sin precio, el mensaje lo dice: una línea faltante se lee como un olvido y
 * facturación termina preguntando.
 */
it('says out loud when a salida has no precio yet', function (): void {
    $programacion = Programacion::factory()->create(['precio_flete' => null]);

    expect(app(AvisoDeSalida::class)->mensajeParaArea($programacion, AreaAviso::factory()->veFlete()->make()))
        ->toContain('Flete: *sin precio acordado*');
});

it('offers the active areas with their numbers, in order', function (): void {
    AreaAviso::factory()->veFlete()->create(['numero' => '950301882', 'orden' => 2]);
    AreaAviso::factory()->create(['nombre' => 'Abastecimiento', 'numero' => '950301881', 'orden' => 1]);

    $avisos = app(AvisoDeSalida::class)->avisosDeArea(Programacion::factory()->create());

    expect(array_column($avisos, 'area'))->toBe(['Abastecimiento', 'Facturación'])
        ->and(array_column($avisos, 'numero'))->toBe(['51950301881', '51950301882']);
});

it('leaves out an area that is turned off or has no valid number', function (): void {
    AreaAviso::factory()->create(['nombre' => 'Abastecimiento', 'numero' => '950301881']);
    AreaAviso::factory()->inactiva()->create(['nombre' => 'Facturación']);
    AreaAviso::factory()->create(['nombre' => 'Centro de Control', 'numero' => '123']);

    expect(app(AvisoDeSalida::class)->avisosDeArea(Programacion::factory()->create()))
        ->toHaveCount(1)
        ->and(app(AvisoDeSalida::class)->avisosDeArea(Programacion::factory()->create())[0]['area'])
        ->toBe('Abastecimiento');
});

/**
 * De dónde carga cada cliente no se escribe al programar: sale de sus GR
 * anteriores, que es donde ya está el dato.
 */
it('deduces where the cliente loads from its past GRs', function (): void {
    $cliente = Cliente::factory()->create(['alias' => 'CRISAR']);

    Viaje::factory()->count(3)->create([
        'cliente_id' => $cliente->id,
        'origen' => 'PARCELA NRO 55 ZONA CAMPO GRAN - HUARAL - HUARAL - LIMA',
        'fecha_traslado' => '2026-09-20',
    ]);

    $programacion = Programacion::factory()->create(['cliente_id' => $cliente->id]);

    expect(app(AvisoDeSalida::class)->lugarDeCarga($programacion))->toBe('HUARAL')
        ->and(app(AvisoDeSalida::class)->mensajeParaArea($programacion->fresh(), AreaAviso::factory()->make()))
        ->toContain('Carga en: *HUARAL*')
        ->toContain('CRISAR');
});

/** Con dos orígenes gana el que más se repite en las últimas guías. */
it('takes the most repeated origen when the cliente loads in two places', function (): void {
    $cliente = Cliente::factory()->create();

    Viaje::factory()->count(3)->create([
        'cliente_id' => $cliente->id,
        'origen' => 'AV. INDUSTRIAL - CHILCA - CAÑETE - LIMA',
    ]);
    Viaje::factory()->create([
        'cliente_id' => $cliente->id,
        'origen' => 'AV. NESTOR GAMBETA - CALLAO - PROV. CONST. DEL CALLAO',
    ]);

    $programacion = Programacion::factory()->create(['cliente_id' => $cliente->id]);

    expect(app(AvisoDeSalida::class)->lugarDeCarga($programacion))->toBe('CHILCA');
});

/** Sin historial no se inventa un lugar: la línea simplemente no sale. */
it('leaves the carga line out for a cliente with no GRs yet', function (): void {
    $programacion = Programacion::factory()->create([
        'cliente_id' => Cliente::factory()->create()->id,
    ]);

    expect(app(AvisoDeSalida::class)->lugarDeCarga($programacion))->toBeNull()
        ->and(app(AvisoDeSalida::class)->mensajeParaArea($programacion->fresh(), AreaAviso::factory()->make()))
        ->not->toContain('Carga en');
});
