<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * De qué tarjeta vino cada línea: para pasar los viáticos ya depositados.
     *
     * @var array<int, int>
     */
    private array $lineaNueva = [];

    /**
     * De qué tarjeta vino cada ruta: para pasar lo ya confirmado en los tickets.
     *
     * @var array<int, int>
     */
    private array $rutaDeTarjeta = [];

    private int $orden = 0;

    /**
     * Junta las opciones de tramo y las tarjetas de la pizarra en rutas.
     *
     * Cada ruta toma su recorrido de la opción y su dotación de la tarjeta
     * que le corresponde. Las tarjetas por peso (Ransa, Tacna) pasan a ser
     * variantes de una sola ruta. Lo que no tiene pareja no se pierde: una
     * opción sin tarjeta queda con galones por definir, y una tarjeta sin
     * opción queda como complemento. Después se pasan las tarjetas ya
     * confirmadas en los tickets y los viáticos depositados.
     */
    public function up(): void
    {
        $tarjetas = DB::table('reglas_abastecimiento')->get()->keyBy('nombre');
        $opciones = DB::table('opciones_tramo')->get()->keyBy('nombre');
        $usadas = [];
        $opcionesUsadas = [];

        foreach ($this->catalogo() as $ruta) {
            $deTarjetas = array_values(array_filter(
                array_map(fn (string $nombre) => $tarjetas->get($nombre), $ruta['tarjetas'] ?? []),
            ));
            $opcion = isset($ruta['opcion']) ? $opciones->get($ruta['opcion']) : null;

            if ($opcion !== null) {
                $opcionesUsadas[] = $opcion->nombre;
            }

            $id = $this->crearRuta([
                'nombre' => $ruta['nombre'],
                'descripcion' => $opcion->descripcion ?? ($ruta['descripcion'] ?? null),
                'sentido' => $ruta['sentido'],
                'es_complemento' => $ruta['complemento'] ?? false,
                'paradas' => $ruta['paradas'],
                'pide_destino' => $ruta['pide_destino'] ?? false,
                'color' => $deTarjetas[0]->color ?? null,
                'nota' => $this->notas($deTarjetas, $ruta['nota'] ?? null),
                'activa' => $opcion === null ? true : (bool) $opcion->activa,
            ]);

            if (($ruta['variantes'] ?? false) && count($deTarjetas) > 1) {
                foreach ($deTarjetas as $indice => $tarjeta) {
                    $variante = DB::table('variantes_ruta')->insertGetId([
                        'ruta_id' => $id,
                        'nombre' => trim((string) last(explode('·', $tarjeta->nombre))),
                        'peso_desde' => $tarjeta->peso_desde,
                        'peso_hasta' => $tarjeta->peso_hasta,
                        'orden' => $indice + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->copiarLineas($tarjeta->id, $id, $variante);
                    $this->rutaDeTarjeta[$tarjeta->id] ??= $id;
                    $usadas[] = $tarjeta->nombre;
                }
            } elseif ($deTarjetas !== []) {
                $this->copiarLineas($deTarjetas[0]->id, $id);
                $this->rutaDeTarjeta[$deTarjetas[0]->id] ??= $id;
                $usadas[] = $deTarjetas[0]->nombre;
            } elseif (! ($ruta['sin_combustible'] ?? false)) {
                // Nadie definió todavía cuánto le toca: queda a la vista.
                $this->linea($id, null, 'combustible', 'Combustible', null, null, null, 1);
            }
        }

        // Lo que la pizarra tenía y ninguna ruta tomó: se conserva como
        // complemento, para que nadie pierda una tarjeta.
        foreach ($tarjetas as $tarjeta) {
            if (in_array($tarjeta->nombre, $usadas, true)) {
                continue;
            }

            $id = $this->crearRuta([
                'nombre' => $tarjeta->nombre,
                'descripcion' => null,
                'sentido' => $tarjeta->sentido,
                'es_complemento' => true,
                'paradas' => [],
                'pide_destino' => false,
                'color' => $tarjeta->color,
                'nota' => $tarjeta->nota,
                'activa' => (bool) $tarjeta->activa,
            ]);
            $this->copiarLineas($tarjeta->id, $id);
            $this->rutaDeTarjeta[$tarjeta->id] ??= $id;
        }

        // Y las opciones que se agregaron a mano y no están en el catálogo.
        foreach ($opciones as $opcion) {
            if (in_array($opcion->nombre, $opcionesUsadas, true)) {
                continue;
            }

            $id = $this->crearRuta([
                'nombre' => $opcion->nombre,
                'descripcion' => $opcion->descripcion,
                'sentido' => $opcion->sentido,
                'es_complemento' => false,
                'paradas' => json_decode($opcion->paradas, true) ?: [],
                'pide_destino' => (bool) $opcion->pide_destino,
                'color' => null,
                'nota' => null,
                'activa' => (bool) $opcion->activa,
            ]);
            $this->linea($id, null, 'combustible', 'Combustible', null, null, null, 1);
        }

        $this->pasarLoConfirmado();
        $this->pasarLosViaticos();
    }

    public function down(): void
    {
        DB::table('rutas_vuelta')->delete();
        DB::table('viaticos')->update(['linea_ruta_id' => null]);
        DB::table('lineas_ruta')->delete();
        DB::table('variantes_ruta')->delete();
        DB::table('rutas')->delete();
    }

    /**
     * Las rutas, en el orden en que se ofrecen. `tarjetas`: de dónde sale la
     * dotación; con `variantes`, cada tarjeta es una variante por peso.
     *
     * @return list<array<string, mixed>>
     */
    private function catalogo(): array
    {
        $p = fn (string $origen, ?string $destino, string $carga, ?string $lugarOrigen = null, ?string $lugarDestino = null): array => array_filter([
            'zona_origen' => $origen,
            'zona_destino' => $destino,
            'tipo_carga' => $carga,
            'lugar_origen' => $lugarOrigen,
            'lugar_destino' => $lugarDestino,
        ], fn ($valor, $clave) => $valor !== null || $clave === 'zona_destino', ARRAY_FILTER_USE_BOTH);

        $ransa = [
            'Materiales Ransa a mina · menos de 5 t',
            'Materiales Ransa a mina · hasta 8 t',
            'Materiales Ransa a mina · hasta 15 t',
            'Materiales Ransa a mina · hasta 20 t',
            'Materiales Ransa a mina · hasta 25 t',
            'Materiales Ransa a mina · más de 25 t',
        ];

        return [
            // Ida
            ['nombre' => 'Concentrado a Pisco', 'opcion' => 'Concentrado a Pisco', 'sentido' => 'ida', 'paradas' => [$p('mina', 'pisco', 'concentrado')], 'tarjetas' => ['Juliaca – Mina – Lima'],
                'nota' => 'La dotación cubre toda la ida hasta Lima: el metálico al Callao o la vacía de Pisco a Lima no suman.'],
            ['nombre' => 'Particular a Lima', 'opcion' => 'Particular a Lima', 'sentido' => 'ida', 'paradas' => [$p('juliaca', 'lima', 'particular')], 'tarjetas' => ['Juliaca a Lima (cal)']],
            ['nombre' => 'Vacía a Lima', 'opcion' => 'Vacía a Lima', 'sentido' => 'ida', 'paradas' => [$p('juliaca', 'lima', 'vacio')]],
            ['nombre' => 'Metálico al Callao', 'opcion' => 'Metálico al Callao', 'sentido' => 'ida', 'complemento' => true, 'paradas' => [$p('pisco', 'lima', 'metalico')], 'sin_combustible' => true,
                'nota' => 'Va incluido en la dotación de «Concentrado a Pisco».'],
            ['nombre' => 'Vacía de Pisco a Lima', 'opcion' => 'Vacía de Pisco a Lima', 'sentido' => 'ida', 'complemento' => true, 'paradas' => [$p('pisco', 'lima', 'vacio')], 'sin_combustible' => true,
                'nota' => 'Va incluido en la dotación de «Concentrado a Pisco».'],

            // Vuelta
            ['nombre' => 'Escoria a mina', 'opcion' => 'Escoria a mina', 'sentido' => 'regreso', 'paradas' => [$p('pisco', 'mina', 'escoria')], 'tarjetas' => ['Retorno Pisco · escoria']],
            ['nombre' => 'Sacos a mina', 'sentido' => 'regreso', 'paradas' => [$p('pisco', 'mina', 'sacos_nuevos')], 'tarjetas' => ['Retorno Pisco · sacos'], 'descripcion' => 'Pisco → Mina'],
            ['nombre' => 'Materiales a mina (Ransa)', 'opcion' => 'Materiales a mina', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'mina', 'materiales')], 'tarjetas' => $ransa, 'variantes' => true],
            ['nombre' => 'Particular a mina', 'opcion' => 'Particular a mina', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'mina', 'particular')], 'tarjetas' => $ransa, 'variantes' => true,
                'nota' => 'Usa la misma dotación por peso que los materiales de Ransa.'],
            ['nombre' => 'Particular a Juliaca', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'juliaca', 'particular', null, 'Juliaca')], 'tarjetas' => ['Retorno con material particular'], 'descripcion' => 'Lima → Juliaca'],
            ['nombre' => 'Particular de Chilca a Juliaca', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'juliaca', 'particular', 'Chilca', 'Juliaca')], 'tarjetas' => ['Retorno de Chilca – Juliaca'], 'descripcion' => 'Chilca → Juliaca'],
            ['nombre' => 'Particular a Cusco', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'cusco', 'particular', null, 'Cusco'), $p('cusco', 'juliaca', 'vacio', 'Cusco', 'Juliaca')], 'tarjetas' => ['Retorno por Cusco'], 'descripcion' => 'Lima → Cusco, y vacía a Juliaca'],
            ['nombre' => 'Particular a Tacna (Pucamarca)', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'tacna', 'particular', null, 'Tacna'), $p('tacna', 'juliaca', 'vacio', 'Tacna', 'Juliaca')],
                'tarjetas' => ['Retorno por Pucamarca – Tacna · hasta 6 t', 'Retorno por Pucamarca – Tacna · más de 6 t'], 'variantes' => true, 'descripcion' => 'Lima → Tacna, y vacía a Juliaca'],
            ['nombre' => 'Particular a Moquegua – Ilo', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'moquegua', 'particular', null, 'Ilo')], 'tarjetas' => ['Retorno por Moquegua – Ilo'],
                'descripcion' => 'Lima → Ilo; de ahí a Juliaca', 'nota' => 'De Ilo a Juliaca: si vuelve con harina, agrega el complemento «Ilo a Juliaca con harina».'],
            ['nombre' => 'Particular a Arequipa', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'arequipa', 'particular', null, 'Arequipa'), $p('arequipa', 'juliaca', 'vacio', 'Arequipa', 'Juliaca')], 'descripcion' => 'Lima → Arequipa, y vacía a Juliaca'],
            ['nombre' => 'Particular a otro destino', 'opcion' => 'Carga particular', 'sentido' => 'regreso', 'paradas' => [$p('lima', null, 'particular')], 'pide_destino' => true],
            ['nombre' => 'Vacía a Juliaca', 'opcion' => 'Vacía a Juliaca', 'sentido' => 'regreso', 'paradas' => [$p('lima', 'juliaca', 'vacio')]],

            // Complementos: se suman al tramo que se esté programando.
            ['nombre' => 'Ilo a Juliaca con harina', 'sentido' => 'regreso', 'complemento' => true, 'paradas' => [$p('moquegua', 'juliaca', 'particular', 'Ilo', 'Juliaca')], 'tarjetas' => ['Retorno de Ilo – Juliaca']],
            ['nombre' => 'Nazca a Juliaca', 'sentido' => null, 'complemento' => true, 'paradas' => [], 'tarjetas' => ['Nazca a Juliaca']],
            ['nombre' => 'Camaná (arroz)', 'sentido' => null, 'complemento' => true, 'paradas' => [], 'tarjetas' => ['Camaná (arroz)']],
            ['nombre' => 'Ida y vuelta Huaral', 'sentido' => null, 'complemento' => true, 'paradas' => [], 'tarjetas' => ['Ida y vuelta Huaral']],
            ['nombre' => 'Retorno vacío Kio – Pisco', 'sentido' => null, 'complemento' => true, 'paradas' => [$p('lima', 'pisco', 'vacio', 'Kio', 'Pisco')], 'tarjetas' => ['Retorno vacío Kio – Pisco']],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearRuta(array $datos): int
    {
        return DB::table('rutas')->insertGetId([
            ...$datos,
            'paradas' => json_encode(array_values($datos['paradas'])),
            'orden' => ++$this->orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copiarLineas(int $tarjetaId, int $rutaId, ?int $varianteId = null): void
    {
        $lineas = DB::table('lineas_regla_abastecimiento')
            ->where('regla_abastecimiento_id', $tarjetaId)
            ->orderBy('orden')
            ->get();

        foreach ($lineas as $linea) {
            $nueva = $this->linea($rutaId, $varianteId, $linea->tipo, $linea->concepto, $linea->punto_abastecimiento_id, $linea->galones, $linea->monto, $linea->orden);
            $this->lineaNueva[$linea->id] ??= $nueva;
        }
    }

    private function linea(int $rutaId, ?int $varianteId, string $tipo, string $concepto, ?int $puntoId, mixed $galones, mixed $monto, int $orden): int
    {
        return DB::table('lineas_ruta')->insertGetId([
            'ruta_id' => $rutaId,
            'variante_ruta_id' => $varianteId,
            'tipo' => $tipo,
            'concepto' => $concepto,
            'punto_abastecimiento_id' => $puntoId,
            'galones' => $galones,
            'monto' => $monto,
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<object>  $tarjetas
     */
    private function notas(array $tarjetas, ?string $propia): ?string
    {
        $notas = array_filter([$propia, ...array_map(fn (object $tarjeta): ?string => data_get($tarjeta, 'nota'), $tarjetas)]);

        return $notas === [] ? null : implode("\n", array_unique($notas));
    }

    /**
     * Las tarjetas confirmadas en los tickets pasan como rutas del ticket,
     * con las líneas que ya tenían (no se refrescan).
     */
    private function pasarLoConfirmado(): void
    {
        foreach (DB::table('dotaciones_tramo')->orderBy('id')->get() as $orden => $dotacion) {
            $lineas = array_map(function (array $linea): array {
                $linea['linea_id'] = $this->lineaNueva[$linea['linea_id'] ?? 0] ?? null;

                return $linea;
            }, json_decode($dotacion->lineas, true) ?: []);

            DB::table('rutas_vuelta')->insert([
                'vuelta_id' => $dotacion->vuelta_id,
                'sentido' => $dotacion->sentido,
                'ruta_id' => $this->rutaDeTarjeta[$dotacion->regla_abastecimiento_id ?? 0] ?? null,
                'variante_ruta_id' => null,
                'es_complemento' => false,
                'nombre' => $dotacion->nombre,
                'color' => $dotacion->color,
                'lineas' => json_encode($lineas),
                'variante_pendiente' => false,
                'orden' => $orden + 1,
                'user_id' => $dotacion->user_id,
                'created_at' => $dotacion->created_at,
                'updated_at' => $dotacion->updated_at,
            ]);
        }
    }

    private function pasarLosViaticos(): void
    {
        foreach ($this->lineaNueva as $vieja => $nueva) {
            DB::table('viaticos')->where('linea_regla_abastecimiento_id', $vieja)->update(['linea_ruta_id' => $nueva]);
        }
    }
};
