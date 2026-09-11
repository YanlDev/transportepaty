<?php

use App\Models\User;
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
| Reloj de las pruebas
|--------------------------------------------------------------------------
|
| La aplicación calcula en UTC pero deriva los días de calendario en hora de
| Lima (ver `App\Services\RelojOperativo`). Entre las 19:00 y la medianoche de
| Lima las dos zonas están en días distintos, así que una prueba que corriera
| en ese rato vería «hoy» distinto según qué reloj mirara y fallaría sola.
|
| Congelarlas a mediodía elimina esa ambigüedad: a las 12:00 UTC son las 07:00
| en Lima, el mismo día en ambas zonas, y una fixture escrita como
| `now()->subDay()` significa exactamente «ayer» en los dos marcos.
|
| El borde en sí no queda sin probar: `RelojOperativoTest` se para a propósito
| en la franja donde las zonas discrepan.
|
*/

pest()->beforeEach(function (): void {
    $this->travelTo(now()->setTime(12, 0));
})->in('Feature');

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
