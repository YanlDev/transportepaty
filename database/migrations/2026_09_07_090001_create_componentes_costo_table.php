<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('componentes_costo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo');
            $table->string('naturaleza');
            $table->string('metodo');
            // Cada método pide campos distintos —una planilla no se parece en
            // nada a un ciclo de neumáticos—, así que las entradas van como
            // JSON y cada método sabe leer las suyas.
            $table->json('entradas');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::table('componentes_costo')->insert($this->semilla());
    }

    public function down(): void
    {
        Schema::dropIfExists('componentes_costo');
    }

    /**
     * La estructura de costos del Excel de MINSUR (agosto 2026), con la cadena
     * que deriva cada tasa en vez de solo su resultado.
     *
     * Dos salvedades sobre las tasas que esto reproduce:
     *
     * - El costo de oportunidad del activo sale en el Excel de una simulación
     *   de valor de mercado por marca y antigüedad. Acá se reconstruye con la
     *   cuenta estándar (depreciación + capital) sobre una unidad nueva, que
     *   cae en S/268.43 contra los S/268.13 del Excel.
     * - Mantenimiento usa el plan de servicios documentado en la hoja de la
     *   ruta Pisco-San Rafael (S/0.2095 por km). La matriz de MINSUR arrastra
     *   S/0.2669 para el mismo concepto sin detallar de dónde sale; se deja el
     *   plan detallado, que es auditable, y las filas se ajustan si hace falta.
     *
     * @return list<array<string, mixed>>
     */
    private function semilla(): array
    {
        $componentes = [
            [
                'nombre' => 'Costo de oportunidad de los activos',
                'tipo' => 'fijo_dia',
                'naturaleza' => 'directo',
                'metodo' => 'activo',
                'entradas' => [
                    'valor_tracto' => 320000,
                    'valor_carreta' => 70000,
                    'valor_residual_pct' => 0.35,
                    'vida_util_anios' => 5,
                    'tasa_anual' => 0.1165,
                ],
            ],
            [
                'nombre' => 'Mano de obra directa (choferes)',
                'tipo' => 'fijo_dia',
                'naturaleza' => 'directo',
                'metodo' => 'planilla_conductor',
                'entradas' => [
                    'sueldo_base' => 3832.375,
                    'seguro_vida_ley' => 17.5,
                    'sctr' => 65,
                    'asignacion_familiar' => 113,
                    'otros' => 0,
                    'remuneraciones_anuales' => 11,
                    'meses_vacaciones' => 1,
                    'gratificaciones' => 2,
                    'cts_meses' => 1.1666667,
                    'essalud_pct' => 0.09,
                    'dias_feriados' => 16,
                    'valor_dia_feriado' => 130.724,
                    // La unidad rueda más días de los que una sola persona
                    // puede manejar: hay relevos.
                    'choferes_por_camion' => 1.15,
                ],
            ],
            [
                'nombre' => 'Mano de obra indirecta',
                'tipo' => 'fijo_dia',
                'naturaleza' => 'indirecto',
                'metodo' => 'prorrateo_anual',
                'entradas' => [
                    'monto_anual' => 1312228,
                    'dedicacion_pct' => 0.75,
                ],
            ],
            [
                'nombre' => 'Otros equipamientos',
                'tipo' => 'fijo_dia',
                'naturaleza' => 'directo',
                'metodo' => 'prorrateo_anual',
                'entradas' => [
                    'monto_anual' => 1849129,
                    'dedicacion_pct' => 1,
                ],
            ],
            [
                'nombre' => 'Seguros',
                'tipo' => 'fijo_dia',
                'naturaleza' => 'indirecto',
                'metodo' => 'prorrateo_anual',
                'entradas' => [
                    'monto_anual' => 1055677,
                    'dedicacion_pct' => 1,
                ],
            ],
            [
                'nombre' => 'Gastos administrativos y otras cargas fijas',
                'tipo' => 'fijo_dia',
                'naturaleza' => 'indirecto',
                'metodo' => 'prorrateo_anual',
                'entradas' => [
                    'monto_anual' => 816091,
                    'dedicacion_pct' => 1,
                ],
            ],
            [
                'nombre' => 'Combustible',
                'tipo' => 'variable_km',
                'naturaleza' => 'directo',
                'metodo' => 'combustible',
                'entradas' => [
                    'localidades' => [
                        ['nombre' => 'Arequipa', 'participacion' => 0.5, 'precio_galon' => 22.30],
                        ['nombre' => 'Nazca', 'participacion' => 0.2, 'precio_galon' => 22.05],
                        ['nombre' => 'Lima', 'participacion' => 0.3, 'precio_galon' => 22.68],
                    ],
                    'precio_incluye_igv' => true,
                    'rendimiento_km_galon' => 7.49,
                    // UREA de las unidades Euro V.
                    'aditivo_precio_galon' => 12,
                    'aditivo_rendimiento_km_galon' => 100,
                ],
            ],
            [
                'nombre' => 'Mantenimiento',
                'tipo' => 'variable_km',
                'naturaleza' => 'directo',
                'metodo' => 'frecuencia_km',
                'entradas' => [
                    'servicios' => [
                        ['concepto' => 'Preventivo menor', 'costo' => 550, 'cada_km' => 12500],
                        ['concepto' => 'Preventivo mayor', 'costo' => 1150, 'cada_km' => 50000],
                        ['concepto' => 'Preventivo mayor (100k)', 'costo' => 1450, 'cada_km' => 100000],
                        ['concepto' => 'Correctivo', 'costo' => 10000, 'cada_km' => 100000],
                        ['concepto' => 'Mano de obra', 'costo' => 350, 'cada_km' => 12500],
                    ],
                ],
            ],
            [
                'nombre' => 'Neumáticos',
                'tipo' => 'variable_km',
                'naturaleza' => 'directo',
                'metodo' => 'ciclo_vida',
                'entradas' => [
                    'vidas' => [
                        ['concepto' => 'Juego nuevo', 'costo' => 23051.60, 'km' => 90000],
                        ['concepto' => 'Primer reencauche', 'costo' => 17322.80, 'km' => 72000],
                        ['concepto' => 'Segundo reencauche', 'costo' => 17322.80, 'km' => 72000],
                    ],
                ],
            ],
        ];

        return array_values(array_map(fn (array $componente, int $indice): array => [
            ...$componente,
            'entradas' => json_encode($componente['entradas']),
            'orden' => $indice + 1,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $componentes, array_keys($componentes)));
    }
};
