<?php

use App\Enums\EstadoEnvio;
use App\Models\EnvioWhatsapp;

beforeEach(function (): void {
    config(['transpaty.whatsapp.token' => 'token-de-prueba']);
});

it('moves an envío forward with the receipts WhatsApp sends', function (): void {
    $envio = EnvioWhatsapp::factory()->enviado('3EB0ABC')->create();

    $this->withToken('token-de-prueba')
        ->postJson(route('whatsapp.recibos'), ['recibos' => [['id' => '3EB0ABC', 'estado' => 'entregado']]])
        ->assertSuccessful();

    expect($envio->fresh())->estado->toBe(EstadoEnvio::Entregado)->entregado_at->not->toBeNull();

    $this->withToken('token-de-prueba')
        ->postJson(route('whatsapp.recibos'), ['recibos' => [['id' => '3EB0ABC', 'estado' => 'leido']]]);

    expect($envio->fresh())->estado->toBe(EstadoEnvio::Leido)->leido_at->not->toBeNull();
});

it('does not move an envío back when a late receipt arrives', function (): void {
    $envio = EnvioWhatsapp::factory()->enviado('3EB0ABC')->create();

    $this->withToken('token-de-prueba')->postJson(route('whatsapp.recibos'), ['recibos' => [['id' => '3EB0ABC', 'estado' => 'leido']]]);
    $this->withToken('token-de-prueba')->postJson(route('whatsapp.recibos'), ['recibos' => [['id' => '3EB0ABC', 'estado' => 'entregado']]]);

    expect($envio->fresh()->estado)->toBe(EstadoEnvio::Leido);
});

it('rejects receipts without the service token', function (?string $token): void {
    $envio = EnvioWhatsapp::factory()->enviado('3EB0ABC')->create();

    $peticion = $token === null ? $this : $this->withToken($token);

    $peticion->postJson(route('whatsapp.recibos'), ['recibos' => [['id' => '3EB0ABC', 'estado' => 'leido']]])
        ->assertUnauthorized();

    expect($envio->fresh()->estado)->toBe(EstadoEnvio::Enviado);
})->with(['sin token' => null, 'token equivocado' => 'otro-token']);
