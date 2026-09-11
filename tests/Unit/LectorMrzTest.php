<?php

use App\Services\LectorMrz;

/**
 * La segunda línea del TD1 con fechas y controles correctos.
 *
 * Nacimiento 1980-05-12 (control 8), sexo M, caducidad 2030-03-23 (control 7),
 * nacionalidad PER. Los controles están calculados con el algoritmo de la
 * OACI: pesos 7-3-1 y módulo 10.
 */
function lineaMrz(string $nacimiento, string $ctrlNacimiento, string $caducidad, string $ctrlCaducidad): string
{
    return $nacimiento.$ctrlNacimiento.'M'.$caducidad.$ctrlCaducidad.'PER'.str_repeat('<', 11).'4';
}

/**
 * El dígito de control de la OACI para un campo, para construir fixtures
 * válidas sin copiarlas a mano de un documento real.
 */
function controlOaci(string $campo): string
{
    $pesos = [7, 3, 1];
    $suma = 0;

    foreach (str_split($campo) as $i => $c) {
        $suma += (int) $c * $pesos[$i % 3];
    }

    return (string) ($suma % 10);
}

function mrzValido(string $nacimiento, string $caducidad): string
{
    return lineaMrz($nacimiento, controlOaci($nacimiento), $caducidad, controlOaci($caducidad));
}

it('lee la caducidad cuando los dos dígitos de control cuadran', function (): void {
    $texto = "IDPER1234567890<<<<<<<<<<<<<<<\n".mrzValido('800512', '300323')."\nAPELLIDO<<NOMBRE<<<<<<<<<<<<<<";

    expect((new LectorMrz)->caducidad($texto)?->toDateString())->toBe('2030-03-23');
});

/**
 * El punto de toda la clase: si el OCR confundió un dígito, el control no
 * cuadra y la fecha se descarta en vez de guardarse mal.
 */
it('descarta la fecha cuando el control de la caducidad no cuadra', function (): void {
    $valido = mrzValido('800512', '300323');
    // Se corrompe el último dígito de control, que es el de la caducidad.
    $corrupto = substr_replace($valido, '0', 14, 1);

    $texto = "IDPER1234567890<<<<<<<<<<<<<<<\n{$corrupto}\nAPELLIDO<<NOMBRE<<<<<<<<<<<<<<";

    expect((new LectorMrz)->caducidad($texto))->toBeNull();
});

/**
 * Que el nacimiento también valide es lo que distingue un MRZ real de una
 * cadena de dígitos que por casualidad encajó en el patrón.
 */
it('descarta la línea cuando el control del nacimiento no cuadra', function (): void {
    $linea = lineaMrz('800512', '0', '300323', controlOaci('300323'));

    expect((new LectorMrz)->caducidad("IDPER<<<<\n{$linea}\nX<<Y"))->toBeNull();
});

it('descarta una fecha que pasa el control pero no existe en el calendario', function (): void {
    // 31 de febrero: el dígito comprueba la transcripción, no el almanaque.
    $texto = "IDPER<<<<\n".mrzValido('800512', '300231')."\nX<<Y";

    expect((new LectorMrz)->caducidad($texto))->toBeNull();
});

it('no encuentra nada en un texto sin banda', function (): void {
    $texto = "REPUBLICA DEL PERU\nDNI 12345678\nFecha de Caducidad 23/03/2030";

    expect((new LectorMrz)->caducidad($texto))->toBeNull();
});

/**
 * El OCR nunca devuelve la línea limpia: mete espacios, se come el arranque y
 * confunde chevrones con letras sueltas. La banda tiene que reconocerse igual.
 */
it('tolera la basura que el OCR mete alrededor de la banda', function (): void {
    $sucia = '  _ '.mrzValido('800512', '300323').' e ';

    $texto = "ruido de cabecera\n{$sucia}\nAPELLIDO<<NOMBRE<<<<<<<<<<<<<<";

    expect((new LectorMrz)->caducidad($texto)?->toDateString())->toBe('2030-03-23');
});

it('encuentra la banda aunque venga en cualquier línea del texto', function (): void {
    $texto = implode("\n", [
        'PERU',
        'APELLIDO<<NOMBRE<<<<<<<<<<<<<<',
        'IDPER1234567890<<<<<<<<<<<<<<<',
        mrzValido('751130', '281001'),
    ]);

    expect((new LectorMrz)->caducidad($texto)?->toDateString())->toBe('2028-10-01');
});
