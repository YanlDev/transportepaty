<?php

use App\Models\Conductor;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    foreach (['admin', 'visor', 'conductor'] as $role) {
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
        'email' => 'nuevo@ejemplo.com',
        'password' => 'contrasena-segura',
        'password_confirmation' => 'contrasena-segura',
        'role' => 'visor',
        'conductor_id' => null,
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

    actingAs(actorConRol('conductor'))
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

it('links a conductor when creating a conductor user', function (): void {
    $conductor = Conductor::factory()->create(['user_id' => null]);

    actingAs(actorConRol('admin'))
        ->post(route('usuarios.store'), datosUsuario([
            'role' => 'conductor',
            'conductor_id' => $conductor->id,
        ]))
        ->assertRedirect(route('usuarios.index'));

    $user = User::where('email', 'nuevo@ejemplo.com')->first();

    expect($conductor->fresh()->user_id)->toBe($user->id);
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
            'email' => '',
            'password' => '',
            'role' => '',
        ]))
        ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
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

it('unlinks the conductor when the role is no longer conductor', function (): void {
    $user = User::factory()->create();
    $user->assignRole('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);

    actingAs(actorConRol('admin'))
        ->put(route('usuarios.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'visor',
        ])
        ->assertRedirect(route('usuarios.index'));

    expect($conductor->fresh()->user_id)->toBeNull();
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

it('lets an admin delete a user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('visor');

    actingAs(actorConRol('admin'))
        ->delete(route('usuarios.destroy', $user))
        ->assertRedirect(route('usuarios.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('unlinks a conductor before deleting the user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('conductor');
    $conductor = Conductor::factory()->create(['user_id' => $user->id]);

    actingAs(actorConRol('admin'))
        ->delete(route('usuarios.destroy', $user))
        ->assertRedirect(route('usuarios.index'));

    expect($conductor->fresh()->user_id)->toBeNull();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('prevents an admin from deleting their own account', function (): void {
    $admin = actorConRol('admin');

    actingAs($admin)
        ->delete(route('usuarios.destroy', $admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('forbids non-admins from deleting users', function (): void {
    $user = User::factory()->create();

    actingAs(actorConRol('visor'))
        ->delete(route('usuarios.destroy', $user))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('has public registration disabled', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', datosUsuario())->assertNotFound();
});
