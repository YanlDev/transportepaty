<?php

use App\Enums\TipoAlerta;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\RevisionTaller;
use App\Models\User;
use App\Models\Vuelta;
use App\Notifications\Alerta;
use App\Services\ResumenTablero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\spy;

/*
 * Las páginas que se refrescan solas preguntan cada pocos segundos. Estas
 * pruebas cuidan que esa pregunta siga siendo liviana: que no rehaga la
 * página entera para traer un dato chico.
 */

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
});

/**
 * Una recarga parcial de Inertia: la misma página, pidiendo solo esos props.
 *
 * @param  list<string>  $solo
 */
function recargaParcial(User $usuario, string $url, string $componente, array $solo): TestResponse
{
    return actingAs($usuario)->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => $componente,
        'X-Inertia-Partial-Data' => implode(',', $solo),
    ]);
}

it('answers the campanita from its own route', function (): void {
    $admin = actorConRol('admin');
    $admin->notify(new Alerta(TipoAlerta::DocumentosPorVencer, 'SOAT por vencer', 'Vence en 5 días.', '/unidades'));

    actingAs($admin);

    getJson(route('alertas.campanita'))
        ->assertSuccessful()
        ->assertJsonPath('sin_leer', 1)
        ->assertJsonPath('recientes.0.titulo', 'SOAT por vencer');
});

it('keeps the campanita route away from guests', function (): void {
    get(route('alertas.campanita'))->assertRedirect(route('login'));
});

it('does not build the tablero when the poll only asks for its firma', function (): void {
    $admin = actorConRol('admin');
    Vuelta::factory()->count(3)->create();

    $tablas = [];
    DB::listen(function ($consulta) use (&$tablas): void {
        $tablas[] = $consulta->sql;
    });

    $respuesta = recargaParcial($admin, route('vueltas.index'), 'vueltas/index', ['firmaTablero', 'guiasPorAsignar'])
        ->assertSuccessful();

    expect($respuesta->json('props.firmaTablero'))->toBeString()->not->toBeEmpty()
        ->and($respuesta->json('props'))->not->toHaveKey('tablero')
        // Las paradas solo se leen al armar el tablero.
        ->and(collect($tablas)->filter(fn (string $sql): bool => str_contains($sql, 'from "paradas_vuelta" where')))->toBeEmpty();
});

it('changes the firma only when something on the tablero changes', function (): void {
    $admin = actorConRol('admin');
    $vuelta = Vuelta::factory()->create();

    $firma = fn (): string => recargaParcial($admin, route('vueltas.index'), 'vueltas/index', ['firmaTablero'])
        ->json('props.firmaTablero');

    $antes = $firma();

    expect($firma())->toBe($antes);

    RevisionTaller::factory()->create(['vuelta_id' => $vuelta->id]);

    expect($firma())->not->toBe($antes);
});

it('sends the tablero and its firma together on a full visit', function (): void {
    Vuelta::factory()->create();

    actingAs(actorConRol('admin'))
        ->get(route('vueltas.index'))
        ->assertInertia(fn ($page) => $page
            ->component('vueltas/index')
            ->has('tablero', 1)
            ->has('firmaTablero'));
});

it('does not rebuild the dashboard resumen when the poll only asks for pendientes', function (): void {
    $admin = actorConRol('admin');
    $resumen = spy(ResumenTablero::class);

    $respuesta = recargaParcial($admin, route('dashboard'), 'dashboard', ['pendientes'])
        ->assertSuccessful();

    expect($respuesta->json('props'))->toHaveKey('pendientes')->not->toHaveKey('resumen');

    $resumen->shouldNotHaveReceived('paraElRango');
    $resumen->shouldNotHaveReceived('metaConcentrado');
});
