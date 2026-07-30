<?php

use App\Enums\TipoAlerta;
use App\Models\User;
use App\Services\Alerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function actorConRol(string $rol): User
{
    return User::factory()->create()->assignRole($rol);
}

/**
 * Códigos de las alertas detectadas, para afirmar sobre ellas sin depender del
 * orden en que los validadores hacen sus comprobaciones.
 *
 * @param  list<Alerta>  $alertas
 * @return list<string>
 */
function tiposDeAlerta(array $alertas): array
{
    return array_map(fn (Alerta $alerta): string => $alerta->tipo->value, $alertas);
}

/**
 * La alerta del tipo indicado, o null si no se detectó.
 *
 * @param  list<Alerta>  $alertas
 */
function alertaDe(array $alertas, TipoAlerta $tipo): ?Alerta
{
    foreach ($alertas as $alerta) {
        if ($alerta->tipo === $tipo) {
            return $alerta;
        }
    }

    return null;
}
