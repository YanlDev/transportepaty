<?php

use App\Models\User;
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
            ->where('roles', ['admin', 'contador', 'visor'])
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

it('lets an admin delete a user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('visor');

    actingAs(actorConRol('admin'))
        ->delete(route('usuarios.destroy', $user))
        ->assertRedirect(route('usuarios.index'));

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
