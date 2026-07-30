<?php

use App\Enums\TipoCarga;
use App\Models\Ubicacion;
use Database\Seeders\UbicacionSeeder;

beforeEach(function (): void {
    $this->seed(UbicacionSeeder::class);
});

it('siembra todos los puntos que citan las reglas de carga-ruta', function (): void {
    // Es el amarre entre las reglas y el catálogo: si alguien renombra un
    // código en el enum o borra un punto del seeder, esto lo delata antes de
    // que el validador empiece a rechazar rutas correctas en silencio.
    $codigosCitados = collect(TipoCarga::cases())
        ->flatMap(fn (TipoCarga $carga): array => $carga->rutasValidas())
        ->flatMap(fn (array $ruta): array => [$ruta['origen'], $ruta['destino']])
        ->unique();

    $codigosSembrados = Ubicacion::query()->pluck('codigo');

    expect($codigosCitados)->not->toBeEmpty()
        ->and($codigosCitados->diff($codigosSembrados)->all())->toBe([]);
});

it('marca a Juliaca como la única zona base', function (): void {
    // La base de la empresa es Juliaca y solo desde ahí se programa la subida
    // a mina. Los demás pueblos del altiplano son puntos de paso del corredor,
    // no lugares desde los que se despacha.
    expect(Ubicacion::query()->zonasBase()->pluck('codigo')->all())->toBe(['juliaca']);
});

it('reconoce a Juliaca como la única base con taller', function (): void {
    $conTaller = Ubicacion::query()->where('tiene_taller', true)->get();

    expect($conTaller->pluck('codigo')->all())->toBe(['juliaca'])
        ->and($conTaller->first()->es_zona_base)->toBeTrue()
        // Ahí las unidades esperan turno para volver a subir, así que un par de
        // días parada no es una unidad detenida.
        ->and($conTaller->first()->permanenciaEsNormal(2))->toBeTrue();
});

it('no le da permanencia habitual a ningún punto fuera de la base', function (): void {
    $conPermanencia = Ubicacion::query()
        ->whereNotNull('dias_permanencia_habitual')
        ->pluck('codigo')
        ->all();

    expect($conPermanencia)->toBe(['juliaca']);
});

it('ordena las zonas de la mina hacia el norte', function (): void {
    $corredor = Ubicacion::query()->enCorredor()->get();
    $zonas = $corredor->pluck('orden_corredor')->all();

    // Las zonas se repiten a propósito: varias ubicaciones comparten tramo.
    // Lo que no puede pasar es que el orden se rompa.
    expect($zonas)->toBe(collect($zonas)->sort()->values()->all())
        ->and($corredor->first()->orden_corredor)->toBe(10)
        ->and($corredor->last()->orden_corredor)->toBe(130);
});

it('marca un solo eje por zona, y todos con coordenadas', function (): void {
    $ejes = Ubicacion::query()->ejesDelCorredor()->get();
    $zonas = $ejes->pluck('orden_corredor');

    expect($zonas->all())->toBe($zonas->unique()->values()->all())
        ->and($ejes->first()->codigo)->toBe('san_rafael')
        ->and($ejes->last()->codigo)->toBe('huaral')
        // Sin coordenadas en el eje no habría cómo medir el tramo.
        ->and($ejes->reject(fn (Ubicacion $eje): bool => $eje->tieneCoordenadas())->all())
        ->toBe([]);
});

it('deja dentro del tramo mina-Pisco a todas las rutas alternativas', function (string $codigo): void {
    $mina = Ubicacion::query()->where('codigo', 'san_rafael')->firstOrFail();
    $pisco = Ubicacion::query()->where('codigo', 'pisco')->firstOrFail();
    $punto = Ubicacion::query()->where('codigo', $codigo)->firstOrFail();

    expect($punto->estaEntre($mina, $pisco))->toBeTrue();
})->with(['juliaca', 'azangaro', 'imata', 'arequipa', 'yura', 'la_joya', 'majes', 'camana', 'nazca']);

it('deja fuera del tramo a los destinos que no pertenecen al corredor', function (string $codigo): void {
    $mina = Ubicacion::query()->where('codigo', 'san_rafael')->firstOrFail();
    $pisco = Ubicacion::query()->where('codigo', 'pisco')->firstOrFail();
    $punto = Ubicacion::query()->where('codigo', $codigo)->firstOrFail();

    expect($punto->estaEntre($mina, $pisco))->toBeFalse();
})->with(['cusco', 'tacna', 'moquegua', 'viru']);

it('siembra sin posición los almacenes cuya ubicación exacta no conocemos', function (): void {
    $ransa = Ubicacion::query()->where('codigo', 'ransa')->firstOrFail();

    expect($ransa->tieneCoordenadas())->toBeFalse()
        ->and($ransa->observaciones)->not->toBeNull();
});

it('siembra los lugares que salen en los reportes pero no supimos ubicar', function (): void {
    // Se guardan para que el importador los reconozca, pero sin zona ni
    // coordenadas: inventarlas desviaría las llegadas estimadas en silencio.
    $porUbicar = Ubicacion::query()
        ->whereNull('orden_corredor')
        ->whereNull('latitud')
        ->get();

    expect($porUbicar->pluck('codigo')->all())->toContain('chavina', 'aychuyo')
        ->and($porUbicar->every(fn (Ubicacion $u): bool => $u->observaciones !== null))
        ->toBeTrue();
});

it('reconoce los textos de la casilla de ubicación que no son lugares', function (string $texto): void {
    expect(Ubicacion::esTextoSinUbicacion($texto))->toBeTrue()
        ->and(Ubicacion::buscarPorNombre($texto))->toBeNull();
})->with(['PROGRAMADO', 'NO CONTESTA', 'DESCANSO', 'VACACIONES', 'INHABILITADO', '46232']);

it('no confunde un lugar de verdad con una marca de estado', function (): void {
    expect(Ubicacion::esTextoSinUbicacion('Juliaca'))->toBeFalse()
        ->and(Ubicacion::esTextoSinUbicacion('Pisco'))->toBeFalse();
});

it('resuelve las variantes de escritura que se sembraron como alias', function (string $texto, string $codigo): void {
    expect(Ubicacion::buscarPorNombre($texto)?->codigo)->toBe($codigo);
})->with([
    ['U.M. SAN RAFAEL', 'san_rafael'],
    ['Mina San Rafael', 'san_rafael'],
    ['Nasca', 'nazca'],
    ['Nazca', 'nazca'],
    ['Azangaro', 'azangaro'],
]);

it('se puede volver a correr sin duplicar el catálogo', function (): void {
    $antes = Ubicacion::query()->count();

    $this->seed(UbicacionSeeder::class);

    expect(Ubicacion::query()->count())->toBe($antes);
});
