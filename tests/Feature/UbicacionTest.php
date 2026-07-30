<?php

use App\Models\Ubicacion;

it('reduce el nombre a su forma comparable', function (string $texto, string $esperado): void {
    expect(Ubicacion::normalizar($texto))->toBe($esperado);
})->with([
    'quita tildes' => ['Azángaro', 'AZANGARO'],
    'sube a mayúsculas' => ['juliaca', 'JULIACA'],
    'quita puntuación' => ['U.M. San Rafael', 'U M SAN RAFAEL'],
    'colapsa espacios' => ['  La   Joya  ', 'LA JOYA'],
    'texto vacío' => ['   ', ''],
]);

it('guarda el nombre normalizado solo, sin que nadie se lo escriba', function (): void {
    $ubicacion = Ubicacion::factory()->create(['nombre' => 'Ocoña']);

    expect($ubicacion->nombre_normalizado)->toBe('OCONA');
});

it('resuelve el nombre venga como venga escrito en el reporte', function (string $texto): void {
    Ubicacion::factory()->create(['codigo' => 'azangaro', 'nombre' => 'Azángaro']);

    expect(Ubicacion::buscarPorNombre($texto)?->codigo)->toBe('azangaro');
})->with(['Azángaro', 'AZANGARO', 'azangaro', ' Azangaro. ']);

it('resuelve un nombre alternativo una vez que se confirmó', function (): void {
    $mina = Ubicacion::factory()->create(['codigo' => 'san_rafael', 'nombre' => 'San Rafael']);

    expect(Ubicacion::buscarPorNombre('U.M. SAN RAFAEL'))->toBeNull();

    $mina->registrarAlias('U.M. SAN RAFAEL');

    expect(Ubicacion::buscarPorNombre('U.M. SAN RAFAEL')?->codigo)->toBe('san_rafael');
});

it('no vuelve a preguntar por un alias ya confirmado', function (): void {
    $mina = Ubicacion::factory()->create(['codigo' => 'san_rafael', 'nombre' => 'San Rafael']);

    $mina->registrarAlias('UM SAN RAFAEL');
    $mina->registrarAlias('um san rafael');

    expect($mina->alias()->count())->toBe(1);
});

it('no registra como alias el propio nombre del punto', function (): void {
    $juliaca = Ubicacion::factory()->create(['nombre' => 'Juliaca']);

    expect($juliaca->registrarAlias('JULIACA'))->toBeNull()
        ->and($juliaca->alias()->count())->toBe(0);
});

it('deja sin resolver lo que no reconoce, en vez de adivinar', function (): void {
    Ubicacion::factory()->create(['nombre' => 'Juliaca']);

    expect(Ubicacion::buscarPorNombre('Grifo del kilómetro 48'))->toBeNull()
        ->and(Ubicacion::buscarPorNombre('0'))->toBeNull()
        ->and(Ubicacion::buscarPorNombre(''))->toBeNull();
});

it('reconoce los puntos que caen dentro del tramo declarado, en los dos sentidos', function (): void {
    $mina = Ubicacion::factory()->enCorredor(10)->create();
    $juliaca = Ubicacion::factory()->enCorredor(60)->create();
    $pisco = Ubicacion::factory()->enCorredor(160)->create();

    expect($juliaca->estaEntre($mina, $pisco))->toBeTrue()
        ->and($juliaca->estaEntre($pisco, $mina))->toBeTrue()
        // Los extremos cuentan: estar en el destino es estar en el tramo.
        ->and($pisco->estaEntre($mina, $pisco))->toBeTrue();
});

it('detecta la unidad que declara una ruta por la que no está pasando', function (): void {
    // El caso que motivó la regla: declarar concentrado de San Rafael a Pisco
    // estando en Arequipa, que es un destino válido pero no pertenece al
    // corredor troncal.
    $mina = Ubicacion::factory()->enCorredor(10)->create(['nombre' => 'San Rafael']);
    $pisco = Ubicacion::factory()->enCorredor(160)->create(['nombre' => 'Pisco']);
    $arequipa = Ubicacion::factory()->create(['nombre' => 'Arequipa']);

    expect($arequipa->estaEnCorredor())->toBeFalse()
        ->and($arequipa->estaEntre($mina, $pisco))->toBeFalse();
});

it('deja fuera del tramo a los puntos del corredor que quedan más allá', function (): void {
    $mina = Ubicacion::factory()->enCorredor(10)->create();
    $pisco = Ubicacion::factory()->enCorredor(160)->create();
    $callao = Ubicacion::factory()->enCorredor(210)->create();

    expect($callao->estaEntre($mina, $pisco))->toBeFalse();
});

it('acepta como normal que la unidad se quede los días de permanencia de la base', function (): void {
    $juliaca = Ubicacion::factory()->conTaller(2)->create(['nombre' => 'Juliaca']);

    expect($juliaca->permanenciaEsNormal(0))->toBeTrue()
        ->and($juliaca->permanenciaEsNormal(1))->toBeTrue()
        ->and($juliaca->permanenciaEsNormal(2))->toBeTrue()
        ->and($juliaca->permanenciaEsNormal(5))->toBeFalse();
});

it('no le da permanencia a los puntos de paso', function (): void {
    // Una unidad detenida en Nazca no está esperando turno: está detenida.
    $nazca = Ubicacion::factory()->enCorredor(150)->create(['nombre' => 'Nazca']);

    expect($nazca->dias_permanencia_habitual)->toBeNull()
        ->and($nazca->permanenciaEsNormal(0))->toBeTrue()
        ->and($nazca->permanenciaEsNormal(1))->toBeFalse();
});

it('distingue los puntos que todavía no se pueden dibujar en el mapa', function (): void {
    expect(Ubicacion::factory()->create()->tieneCoordenadas())->toBeTrue()
        ->and(Ubicacion::factory()->sinCoordenadas()->create()->tieneCoordenadas())->toBeFalse();
});

it('lista las zonas base y el corredor en orden', function (): void {
    Ubicacion::factory()->zonaBase()->enCorredor(60)->create(['nombre' => 'Juliaca']);
    Ubicacion::factory()->enCorredor(10)->create(['nombre' => 'San Rafael']);
    Ubicacion::factory()->create(['nombre' => 'Arequipa']);

    expect(Ubicacion::query()->zonasBase()->pluck('nombre')->all())->toBe(['Juliaca'])
        ->and(Ubicacion::query()->enCorredor()->pluck('nombre')->all())
        ->toBe(['San Rafael', 'Juliaca']);
});
