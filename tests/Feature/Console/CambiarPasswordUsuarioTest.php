<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('changes the password of an account found by username', function (): void {
    $user = User::factory()->create(['username' => 'jperez']);

    $this->artisan('usuario:password', [
        'usuario' => 'jperez',
        '--password' => 'contrasena-nueva',
    ])->assertSuccessful();

    expect(Hash::check('contrasena-nueva', $user->fresh()->password))->toBeTrue();
});

/**
 * La salida de emergencia del admin: se lo encuentra por su correo, que es con
 * lo que entra, sin tener que recordar su usuario.
 */
it('also finds the account by email', function (): void {
    $user = User::factory()->create(['email' => 'jefe@transpaty.com']);

    $this->artisan('usuario:password', [
        'usuario' => 'jefe@transpaty.com',
        '--password' => 'contrasena-nueva',
    ])->assertSuccessful();

    expect(Hash::check('contrasena-nueva', $user->fresh()->password))->toBeTrue();
});

it('fails when no account matches', function (): void {
    $this->artisan('usuario:password', [
        'usuario' => 'fantasma',
        '--password' => 'contrasena-nueva',
    ])->assertFailed();
});

it('refuses a password that breaks the rules', function (): void {
    $user = User::factory()->create(['username' => 'jperez']);
    $anterior = $user->password;

    $this->artisan('usuario:password', [
        'usuario' => 'jperez',
        '--password' => 'corta',
    ])->assertFailed();

    expect($user->fresh()->password)->toBe($anterior);
});
