<?php

use App\Enums\TipoCarga;
use App\Models\Viaje;

/**
 * @param  list<array{numero: string, ruc: string}>|null  $guiasRemitente
 * @return array<string, mixed>
 */
function viajeMinsurParaClasificar(string $numeroGr, string $tipoCarga, ?array $guiasRemitente): array
{
    return [
        'numero_gr' => $numeroGr,
        'fecha_emision' => '2026-08-01 08:00:00',
        'fecha_traslado' => '2026-08-01',
        'origen' => 'CALLAO',
        'destino' => 'PUNO',
        'tipo_carga' => $tipoCarga,
        'cliente' => 'MINSUR S.A.',
        'cliente_ruc' => TipoCarga::RUC_MINSUR,
        'destinatario' => 'MINSUR S.A.',
        'guias_remitente' => $guiasRemitente,
        'peso' => 1000,
        'unidad_peso' => 'KG',
        'placa_tracto' => 'ABC123',
        'conductor_nombre' => 'Juan Perez',
    ];
}

it('reclasifica un viaje de Minsur en Particular cuya guía remitente tiene una serie reconocida', function (): void {
    $viaje = Viaje::create(viajeMinsurParaClasificar('EG03-00000001', 'particular', [
        ['numero' => 'T007 - 9609', 'ruc' => TipoCarga::RUC_MINSUR],
    ]));

    $this->artisan('transpaty:clasificar-carga-minsur')->assertSuccessful();

    expect($viaje->refresh()->tipo_carga)->toBe(TipoCarga::Concentrado);
});

it('no toca un viaje que ya tiene un tipo de carga distinto de Particular', function (): void {
    $viaje = Viaje::create(viajeMinsurParaClasificar('EG03-00000002', 'metalico', [
        ['numero' => 'T007 - 9610', 'ruc' => TipoCarga::RUC_MINSUR],
    ]));

    $this->artisan('transpaty:clasificar-carga-minsur')->assertSuccessful();

    expect($viaje->refresh()->tipo_carga)->toBe(TipoCarga::Metalico);
});

it('no escribe nada en --dry-run', function (): void {
    $viaje = Viaje::create(viajeMinsurParaClasificar('EG03-00000003', 'particular', [
        ['numero' => 'T004 - 3548', 'ruc' => TipoCarga::RUC_MINSUR],
    ]));

    $this->artisan('transpaty:clasificar-carga-minsur', ['--dry-run' => true])->assertSuccessful();

    expect($viaje->refresh()->tipo_carga)->toBe(TipoCarga::Particular);
});

it('deja Particular cuando la serie de la guía remitente no se reconoce', function (): void {
    $viaje = Viaje::create(viajeMinsurParaClasificar('EG03-00000004', 'particular', [
        ['numero' => 'T104 - 2132', 'ruc' => TipoCarga::RUC_MINSUR],
    ]));

    $this->artisan('transpaty:clasificar-carga-minsur')->assertSuccessful();

    expect($viaje->refresh()->tipo_carga)->toBe(TipoCarga::Particular);
});
