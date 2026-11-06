<?php

use App\Enums\RolBase;
use App\Models\ConfiguracionAlerta;
use App\Models\User;
use App\Services\Alertas;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'contador'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function datosUsuario(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nuevo Usuario',
        'username' => 'nuevo_usuario',
        'email' => 'nuevo@ejemplo.com',
        'password' => 'contrasena-segura',
        'password_confirmation' => 'contrasena-segura',
        'role' => 'visor',
    ], $overrides);
}

it('redirects guests to login', function (): void {
    $this->get(route('usuarios.index'))->assertRedirect(route('login'));
});

it('lets an admin see the user list', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('usuarios.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('usuarios/index')
            ->has('usuarios.data', 1)
        );
});

it('forbids non-admins from the user list', function (): void {
    actingAs(actorConRol('visor'))
        ->get(route('usuarios.index'))
        ->assertForbidden();

    actingAs(actorConRol('contador'))
        ->get(route('usuarios.index'))
        ->assertForbidden();
});

it('lets an admin create a verified user with a role', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['role' => 'visor']))
        ->assertRedirect(route('usuarios.index'));

    $user = User::where('email', 'nuevo@ejemplo.com')->first();

    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasRole('visor'))->toBeTrue();
});

it('rejects the retired conductor role', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['role' => 'conductor']))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'nuevo@ejemplo.com']);
});

it('only offers the roles that exist', function (): void {
    actingAs(actorConRol('admin'))
        ->get(route('usuarios.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('usuarios/create')
            // Los roles base, más el de prueba de solo lectura.
            ->where('roles', fn ($roles): bool => collect($roles)->pluck('name')->sort()->values()->all() === collect([...RolBase::valores(), 'visor'])->sort()->values()->all())
            ->where('roles', fn ($roles): bool => collect(collect($roles)->firstWhere('name', 'contador')['permisos'])->contains('viajes.ver'))
        );
});

it('forbids non-admins from creating users', function (): void {
    actingAs(actorConRol('visor'))
        ->post(route('usuarios.store'), datosUsuario())
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'nuevo@ejemplo.com']);
});

it('validates required fields when creating', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario([
            'name' => '',
            'username' => '',
            'password' => '',
            'role' => '',
        ]))
        ->assertSessionHasErrors(['name', 'username', 'password', 'role']);
});

it('creates an account without an email', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario([
            'role' => 'visor',
            'email' => '',
        ]))
        ->assertRedirect(route('usuarios.index'));

    $user = User::where('username', 'nuevo_usuario')->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBeNull();
});

it('creates an admin without an email too, since nobody logs in with it', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario([
            'role' => 'admin',
            'email' => '',
        ]))
        ->assertRedirect(route('usuarios.index'));

    $user = User::where('username', 'nuevo_usuario')->first();

    expect($user->email)->toBeNull();
    expect($user->hasRole('admin'))->toBeTrue();
});

it('lowercases the username so it matches what the login sends', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['username' => 'JPerez']))
        ->assertRedirect(route('usuarios.index'));

    $this->assertDatabaseHas('users', ['username' => 'jperez']);
});

it('rejects a duplicate username', function (): void {
    User::factory()->create(['username' => 'tomado']);

    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['username' => 'tomado']))
        ->assertSessionHasErrors('username');
});

it('rejects a username that could be confused with an email', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['username' => 'j@ejemplo.com']))
        ->assertSessionHasErrors('username');
});

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'tomado@ejemplo.com']);

    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['email' => 'tomado@ejemplo.com']))
        ->assertSessionHasErrors('email');
});

it('rejects an unknown role', function (): void {
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario(['role' => 'superuser']))
        ->assertSessionHasErrors('role');
});

it('lets an admin update a user email and role', function (): void {
    $user = User::factory()->create();
    $user->assignRole('visor');

    actingAs(actorConRol('admin'))
        ->put(route('usuarios.update', $user), [
            'name' => 'Nombre Editado',
            'username' => 'editado',
            'email' => 'editado@ejemplo.com',
            'role' => 'admin',
        ])
        ->assertRedirect(route('usuarios.index'));

    $user->refresh();

    expect($user->name)->toBe('Nombre Editado');
    expect($user->email)->toBe('editado@ejemplo.com');
    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('visor'))->toBeFalse();
});

it('lets an admin reset a user password', function (): void {
    $user = User::factory()->create();

    actingAs(actorConRol('admin'))
        ->put(route('usuarios.password.update', $user), [
            'password' => 'nueva-contrasena',
            'password_confirmation' => 'nueva-contrasena',
        ])
        ->assertRedirect(route('usuarios.index'));

    expect(Hash::check('nueva-contrasena', $user->fresh()->password))->toBeTrue();
});

it('gives a user a leave instead of deleting them, and they cannot log in anymore', function (): void {
    $user = User::factory()->create(['username' => 'dejo', 'password' => 'secreta-123']);
    $user->assignRole('contador');

    actingAs(actorConRol('admin'))
        ->post(route('usuarios.baja', $user), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');
    actingAs(actorConRol('admin'))
        ->post(route('usuarios.baja', $user), ['motivo' => 'Dejó la empresa'])
        ->assertSessionHas('toast.type', 'success');

    $user->refresh();
    expect($user->estaDeBaja())->toBeTrue()
        ->and($user->motivo_baja)->toBe('Dejó la empresa');

    auth()->logout();
    $this->post(route('login.store'), ['username' => 'dejo', 'password' => 'secreta-123'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('logs out a user that was given a leave while their session was open', function (): void {
    $user = actorConRol('contador');
    $user->forceFill(['baja_at' => now()])->save();

    actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('brings a user back from a leave', function (): void {
    $user = User::factory()->create(['baja_at' => now(), 'motivo_baja' => 'Vacaciones largas']);

    actingAs(actorConRol('admin'))->post(route('usuarios.reactivar', $user))->assertSessionHas('toast.type', 'success');

    expect($user->fresh()->estaDeBaja())->toBeFalse();
});

it('does not give a leave to oneself or by a non-admin', function (): void {
    $admin = actorConRol('admin');
    $otro = User::factory()->create();

    actingAs($admin)->post(route('usuarios.baja', $admin), ['motivo' => 'x'])->assertForbidden();
    actingAs(actorConRol('visor'))->post(route('usuarios.baja', $otro), ['motivo' => 'x'])->assertForbidden();

    expect($admin->fresh()->estaDeBaja())->toBeFalse()
        ->and($otro->fresh()->estaDeBaja())->toBeFalse();
});

it('does not send alerts to users on leave', function (): void {
    actorConRol('contador')->forceFill(['baja_at' => now()])->save();
    $activo = actorConRol('contador');

    $destinatarios = app(Alertas::class)->destinatarios(ConfiguracionAlerta::factory()->make(['roles' => ['contador'], 'usuarios' => []]));

    expect($destinatarios->pluck('id')->all())->toBe([$activo->id]);
});

it('has public registration disabled', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', datosUsuario())->assertNotFound();
});
