<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La pizarra de abastecimiento tal como está en la oficina (foto del
     * 26-10-2026), menos Desaguadero, que todavía no se ve. Donde la pizarra
     * no es del todo clara, el dato va en la nota de la tarjeta para que se
     * confirme desde la configuración en vez de inventarlo.
     *
     * Las tarjetas que no tienen trayecto definido (sin zonas, carga ni
     * lugar) no se aplican solas: quedan como referencia.
     */
    public function up(): void
    {
        $ahora = now();

        $puntos = [
            ['nombre' => 'Kio', 'zona' => 'lima', 'detalle' => 'Grifo y cochera a la entrada de Lima'],
            ['nombre' => 'Mina', 'zona' => 'mina', 'detalle' => null],
            ['nombre' => 'Arequipa', 'zona' => 'sur', 'detalle' => null],
            ['nombre' => 'Nazca', 'zona' => null, 'detalle' => null],
            ['nombre' => 'Mellizas', 'zona' => null, 'detalle' => null],
            ['nombre' => 'Juliaca', 'zona' => 'juliaca', 'detalle' => null],
            ['nombre' => 'Lima', 'zona' => 'lima', 'detalle' => null],
        ];

        $idPunto = [];

        foreach ($puntos as $orden => $punto) {
            $idPunto[$punto['nombre']] = DB::table('puntos_abastecimiento')->insertGetId([
                ...$punto, 'activo' => true, 'orden' => $orden + 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        $combustible = fn (string $concepto, ?string $punto, ?float $galones, bool $lleno = false): array => [
            'tipo' => 'combustible', 'concepto' => $concepto, 'punto' => $punto, 'galones' => $galones, 'tanque_lleno' => $lleno, 'monto' => null,
        ];
        $viatico = fn (string $concepto, float $monto): array => [
            'tipo' => 'viatico', 'concepto' => $concepto, 'punto' => null, 'galones' => null, 'tanque_lleno' => false, 'monto' => $monto,
        ];

        $reglas = [
            [
                'nombre' => 'Juliaca – Mina – Lima', 'color' => 'rosa',
                'sentido' => 'ida', 'zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado',
                'nota' => 'En la pizarra figura además «Cargado escoria-mina: 110 gal».',
                'lineas' => [
                    $combustible('Mina', 'Mina', 100),
                    $combustible('Arequipa', 'Arequipa', 120),
                    $viatico('Viático mina', 143),
                    $viatico('Viático Lima', 700),
                ],
            ],
            [
                'nombre' => 'Retorno con material particular', 'color' => 'verde',
                'sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'juliaca', 'tipo_carga' => 'particular',
                'nota' => 'Según guía o pesaje. En Arequipa: carga completa 190 gal; sin metálicos 180 gal; si cargan metálicos, +10 gal.',
                'lineas' => [
                    $combustible('Tanque full', 'Kio', null, true),
                    $combustible('Arequipa (carga completa)', 'Arequipa', 190),
                    $viatico('Viáticos hasta Juliaca', 640),
                ],
            ],
            ...array_map(fn (array $tramo): array => [
                'nombre' => "Materiales Ransa a mina · {$tramo[2]}", 'color' => 'verde',
                'sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'materiales',
                'peso_desde' => $tramo[0], 'peso_hasta' => $tramo[1],
                'lineas' => [$combustible('Total', null, $tramo[3])],
            ], [
                [null, 5, 'menos de 5 t', 110],
                [5, 8, 'hasta 8 t', 120],
                [8, 15, 'hasta 15 t', 130],
                [15, 20, 'hasta 20 t', 150],
                [20, 25, 'hasta 25 t', 190],
                [25, null, 'más de 25 t', 190],
            ]),
            [
                'nombre' => 'Retorno Pisco · escoria', 'color' => 'rosa',
                'sentido' => 'regreso', 'zona_origen' => 'pisco', 'zona_destino' => 'mina', 'tipo_carga' => 'escoria',
                'lineas' => [$combustible('Escoria (total)', null, 130), $viatico('Viáticos', 180)],
            ],
            [
                'nombre' => 'Retorno Pisco · sacos', 'color' => 'rosa',
                'sentido' => 'regreso', 'zona_origen' => 'pisco', 'zona_destino' => 'mina', 'tipo_carga' => 'sacos_nuevos',
                'nota' => 'Sacos de 5 a 6 toneladas.',
                'lineas' => [$combustible('Sacos (total)', null, 60), $viatico('Viáticos', 180)],
            ],
            [
                'nombre' => 'Retorno de Chilca – Juliaca', 'color' => 'verde',
                'sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'juliaca', 'tipo_carga' => 'particular', 'lugares' => 'CHILCA',
                'lineas' => [
                    $combustible('Mellizas', 'Mellizas', 40),
                    $combustible('Nazca', 'Nazca', 100),
                    $combustible('Arequipa', 'Arequipa', 40),
                    $viatico('Viáticos', 450),
                ],
            ],
            [
                'nombre' => 'Retorno por Cusco', 'color' => 'morado',
                'sentido' => 'regreso', 'zona_origen' => 'lima', 'zona_destino' => 'cusco', 'tipo_carga' => 'particular',
                'nota' => 'La pizarra no tiene el monto de viáticos.',
                'lineas' => [
                    $combustible('Tanque full', 'Kio', null, true),
                    $combustible('Nazca (total)', 'Nazca', 200),
                ],
            ],
            [
                'nombre' => 'Ida y vuelta Huaral', 'color' => 'naranja',
                'zona_origen' => 'lima', 'zona_destino' => 'lima', 'lugares' => 'HUARAL',
                'lineas' => [$combustible('Lima', 'Lima', 20), $viatico('Viático', 180)],
            ],
            [
                'nombre' => 'Retorno por Pucamarca – Tacna · hasta 6 t', 'color' => 'azul',
                'sentido' => 'regreso', 'zona_destino' => 'sur', 'lugares' => 'TACNA', 'peso_hasta' => 6,
                'lineas' => [$combustible('6 toneladas', null, 60), $viatico('Viáticos', 200)],
            ],
            [
                'nombre' => 'Retorno por Pucamarca – Tacna · más de 6 t', 'color' => 'azul',
                'sentido' => 'regreso', 'zona_destino' => 'sur', 'lugares' => 'TACNA', 'peso_desde' => 6,
                'nota' => 'En la pizarra: 31 toneladas, 80 gal.',
                'lineas' => [$combustible('Hasta 31 toneladas', null, 80), $viatico('Viáticos', 200)],
            ],
            [
                'nombre' => 'Retorno por Moquegua – Ilo', 'color' => 'turquesa',
                'sentido' => 'regreso', 'zona_destino' => 'sur', 'lugares' => 'MOQUEGUA, ILO',
                'nota' => 'En la pizarra: «Kio tanque full · 200 gal».',
                'lineas' => [$combustible('Tanque full', 'Kio', null, true), $viatico('Viáticos (cuando van)', 650)],
            ],
            [
                'nombre' => 'Retorno de Ilo – Juliaca', 'color' => 'rosa',
                'zona_origen' => 'sur', 'zona_destino' => 'juliaca', 'lugares' => 'ILO',
                'lineas' => [$combustible('Cargado de harina', null, 40), $viatico('Viático', 120)],
            ],
            [
                'nombre' => 'Nazca a Juliaca', 'color' => 'gris',
                'zona_destino' => 'juliaca', 'lugares' => 'NAZCA',
                'lineas' => [$combustible('Nazca a Juliaca', null, 80)],
            ],
            [
                'nombre' => 'Juliaca a Lima (cal)', 'color' => 'gris',
                'sentido' => 'ida', 'zona_origen' => 'juliaca', 'zona_destino' => 'lima', 'tipo_carga' => 'particular',
                'lineas' => [$combustible('Juliaca a Lima', null, 180)],
            ],
            [
                'nombre' => 'Camaná (arroz)', 'color' => 'gris',
                'lugares' => 'CAMANA',
                'lineas' => [$combustible('Camaná', null, 50)],
            ],
            [
                'nombre' => 'Retorno vacío Kio – Pisco', 'color' => 'gris',
                'nota' => 'En la pizarra figura «S/ 500.00»: confirmar si son viáticos o galones. Sin trayecto: no se aplica sola.',
                'lineas' => [$viatico('Retorno vacío', 500)],
            ],
        ];

        foreach ($reglas as $orden => $regla) {
            $id = DB::table('reglas_abastecimiento')->insertGetId([
                'nombre' => $regla['nombre'],
                'color' => $regla['color'],
                'sentido' => $regla['sentido'] ?? null,
                'zona_origen' => $regla['zona_origen'] ?? null,
                'zona_destino' => $regla['zona_destino'] ?? null,
                'tipo_carga' => $regla['tipo_carga'] ?? null,
                'lugares' => $regla['lugares'] ?? null,
                'peso_desde' => $regla['peso_desde'] ?? null,
                'peso_hasta' => $regla['peso_hasta'] ?? null,
                'nota' => $regla['nota'] ?? null,
                'activa' => true,
                'orden' => $orden + 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            foreach ($regla['lineas'] as $ordenLinea => $linea) {
                DB::table('lineas_regla_abastecimiento')->insert([
                    'regla_abastecimiento_id' => $id,
                    'tipo' => $linea['tipo'],
                    'concepto' => $linea['concepto'],
                    'punto_abastecimiento_id' => $linea['punto'] === null ? null : $idPunto[$linea['punto']],
                    'galones' => $linea['galones'],
                    'tanque_lleno' => $linea['tanque_lleno'],
                    'monto' => $linea['monto'],
                    'orden' => $ordenLinea + 1,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        DB::table('ajustes')->updateOrInsert(['clave' => 'galones_tanque_lleno'], ['valor' => null]);
    }

    public function down(): void
    {
        DB::table('lineas_regla_abastecimiento')->delete();
        DB::table('reglas_abastecimiento')->delete();
        DB::table('puntos_abastecimiento')->delete();
        DB::table('ajustes')->where('clave', 'galones_tanque_lleno')->delete();
    }
};
