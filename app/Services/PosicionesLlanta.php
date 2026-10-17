<?php

namespace App\Services;

use App\Enums\TipoVehiculo;

/**
 * Dónde va cada llanta. La numeración es la que usa el taller: de adelante
 * hacia atrás y, en cada eje, de izquierda a derecha mirando la unidad desde
 * atrás (exterior izquierda, interior izquierda, interior derecha, exterior
 * derecha).
 *
 * - **Tracto 6x4:** el eje delantero simple (1 y 2) y dos ejes motrices
 *   dobles (3 a 6 y 7 a 10).
 * - **Carreta:** tres ejes dobles (1 a 12) y dos de repuesto (R1 y R2).
 *
 * @phpstan-type Posicion array{codigo: string, eje: int, nombre: string}
 */
class PosicionesLlanta
{
    private const LADOS_DOBLE = ['exterior izquierda', 'interior izquierda', 'interior derecha', 'exterior derecha'];

    /**
     * @return list<Posicion>
     */
    public static function para(TipoVehiculo $tipo): array
    {
        return match ($tipo) {
            TipoVehiculo::Tracto => [
                ['codigo' => '1', 'eje' => 1, 'nombre' => 'Delantera izquierda'],
                ['codigo' => '2', 'eje' => 1, 'nombre' => 'Delantera derecha'],
                ...self::ejeDoble(2, 3),
                ...self::ejeDoble(3, 7),
            ],
            TipoVehiculo::Carreta => [
                ...self::ejeDoble(1, 1),
                ...self::ejeDoble(2, 5),
                ...self::ejeDoble(3, 9),
                ['codigo' => 'R1', 'eje' => 0, 'nombre' => 'Repuesto 1'],
                ['codigo' => 'R2', 'eje' => 0, 'nombre' => 'Repuesto 2'],
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function codigos(TipoVehiculo $tipo): array
    {
        return array_column(self::para($tipo), 'codigo');
    }

    /**
     * @return list<Posicion>
     */
    private static function ejeDoble(int $eje, int $primera): array
    {
        $posiciones = [];

        foreach (self::LADOS_DOBLE as $indice => $lado) {
            $posiciones[] = [
                'codigo' => (string) ($primera + $indice),
                'eje' => $eje,
                'nombre' => "Eje {$eje}, {$lado}",
            ];
        }

        return $posiciones;
    }
}
