<?php

use App\Enums\EstadoValeCompra;
use App\Models\ValeCompra;

/*
 * Quien pide una compra también puede anotar su pago (la asistente de
 * gerencia pide y sube el voucher), siempre que la haya aprobado otra
 * persona: el control está en la aprobación.
 */
it('lets the requester record the payment once someone else approved it', function (): void {
    $asistente = actorConRol('gerencia');
    $gerente = actorConRol('gerencia');

    $pedido = ValeCompra::factory()->create(['solicitado_por' => $asistente->id]);

    // Sin aprobar no hay nada que pagar.
    expect($asistente->can('pagar', $pedido))->toBeFalse();

    $pedido->update(['estado' => EstadoValeCompra::Aprobado, 'aprobado_por' => $gerente->id, 'aprobado_at' => now()]);

    expect($asistente->can('pagar', $pedido))->toBeTrue()
        ->and($gerente->can('pagar', $pedido))->toBeTrue();
});

it('never lets the same person request, approve and pay', function (): void {
    $gerente = actorConRol('gerencia');

    $pedido = ValeCompra::factory()->create([
        'solicitado_por' => $gerente->id,
        'estado' => EstadoValeCompra::Aprobado,
        'aprobado_por' => $gerente->id,
        'aprobado_at' => now(),
    ]);

    expect($gerente->can('pagar', $pedido))->toBeFalse();
});

it('still needs the permission to record payments', function (): void {
    $almacen = actorConRol('almacen');

    $pedido = ValeCompra::factory()->create([
        'solicitado_por' => $almacen->id,
        'estado' => EstadoValeCompra::Aprobado,
        'aprobado_por' => actorConRol('gerencia')->id,
        'aprobado_at' => now(),
    ]);

    expect($almacen->can('pagar', $pedido))->toBeFalse();
});
