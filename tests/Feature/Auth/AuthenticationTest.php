<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'visor'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }
    }

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    /**
     * El campo del formulario tiene que llamarse como el que espera Fortify.
     * Si dejan de coincidir, el login deja de funcionar sin que falle nada más:
     * llega una petición sin identificador y devuelve «credenciales inválidas»
     * contra cualquier contraseña, que es el peor error posible de diagnosticar.
     */
    public function test_the_login_form_posts_the_field_fortify_expects()
    {
        $formulario = file_get_contents(resource_path('js/pages/auth/login.tsx'));

        $this->assertStringContainsString(
            'name="'.Fortify::username().'"',
            $formulario,
        );
    }

    public function test_users_can_authenticate_with_their_username()
    {
        $user = User::factory()->sinCorreo()->create();
        $user->assignRole('visor');

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_username_is_case_insensitive()
    {
        $user = User::factory()->create(['username' => 'jperez']);

        $this->post(route('login.store'), [
            'username' => 'JPerez',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * El correo no abre ninguna puerta, ni siquiera la del admin: es un dato de
     * contacto. Al sistema se entra con el usuario y nada más.
     */
    public function test_nobody_authenticates_with_their_email()
    {
        foreach (['admin', 'visor'] as $rol) {
            $user = User::factory()->create();
            $user->assignRole($rol);

            $this->post(route('login.store'), [
                'username' => $user->email,
                'password' => 'password',
            ]);

            $this->assertGuest();
        }
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    /**
     * La cuenta sin correo verificado tiene que poder usar el sistema. Cuando
     * el modelo implementaba `MustVerifyEmail`, tres cuentas reales de
     * producción quedaron encerradas en la pantalla de verificación, con un
     * correo que nunca llegaba porque no es la credencial de entrada.
     */
    public function test_an_account_without_a_verified_email_can_use_the_app()
    {
        $user = User::factory()->sinCorreo()->create(['email_verified_at' => null]);
        $user->assignRole('visor');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->username, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
