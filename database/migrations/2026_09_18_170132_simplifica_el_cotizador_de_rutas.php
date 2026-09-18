<?php

use App\Enums\MetodoCosto;
use App\Models\ParametroFlota;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El cotizador vuelve a la hoja con la que la casa cotiza de verdad: una tasa
 * por línea (S/ por día o S/ por km), días y kilómetros de la ruta, y un margen
 * sobre el precio de venta.
 *
 * Las tasas dejan de salir solas de las cuentas de cada método (planilla,
 * depreciación, prorrateo sobre la flota): ahora se escriben, y el método queda
 * como calculadora de apoyo. Peajes, viáticos y demás gastos del tramo dejan de
 * cargarse uno por uno y pasan a ser una línea más, por kilómetro, como en la
 * hoja.
 */
return new class extends Migration
{
    /**
     * Las líneas sembradas, con el nombre y la tasa que tienen en la hoja de
     * cotización de rutas (setiembre 2026).
     *
     * @var array<string, array{0: string, 1: float}>
     */
    private const TASAS_DE_LA_HOJA = [
        'Costo de oportunidad de los activos' => ['Costo de oportunidad de los activos (tracto + carreta)', 268.13],
        'Mano de obra directa (choferes)' => ['Mano de obra directa (choferes)', 258.94],
        'Mano de obra indirecta' => ['Mano de obra indirecta', 32.47],
        'Otros equipamientos' => ['Otros equipamientos', 61.00],
        'Seguros' => ['Seguros', 23.45],
        'Gastos administrativos y otras cargas fijas' => ['Gastos administrativos y otras cargas fijas', 4.78],
        'Combustible' => ['Consumo de combustible', 2.52],
        'Mantenimiento' => ['Mantenimiento de vehículos', 0.24],
        'Neumáticos' => ['Desgaste y reposición de neumáticos', 0.21],
    ];

    public function up(): void
    {
        Schema::table('componentes_costo', function (Blueprint $table): void {
            $table->decimal('tasa', 10, 4)->default(0)->after('metodo');
        });

        $this->fijarTasas();

        Schema::table('parametros_flota', function (Blueprint $table): void {
            $table->dropColumn('viatico_dia');
        });

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->decimal('total_fijo', 12, 2)->default(0)->after('margen_pct');
            $table->decimal('total_variable', 12, 2)->default(0)->after('total_fijo');
        });

        $this->reescribirCotizaciones();

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropColumn([
                'peajes', 'viaticos', 'alojamiento', 'cochera', 'carga_descarga',
                'otros_ruta', 'total_directo', 'total_indirecto',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            foreach (['peajes', 'viaticos', 'alojamiento', 'cochera', 'carga_descarga', 'otros_ruta'] as $campo) {
                $table->decimal($campo, 10, 2)->default(0);
            }

            $table->decimal('total_directo', 12, 2)->default(0);
            $table->decimal('total_indirecto', 12, 2)->default(0);
            $table->dropColumn(['total_fijo', 'total_variable']);
        });

        Schema::table('parametros_flota', function (Blueprint $table): void {
            $table->decimal('viatico_dia', 10, 2)->default(50);
        });

        Schema::table('componentes_costo', function (Blueprint $table): void {
            $table->dropColumn('tasa');
        });
    }

    /**
     * Las líneas de la hoja toman su tasa y su nombre; cualquier otra conserva
     * la que venía calculando, para que nada cambie de precio sin querer.
     */
    private function fijarTasas(): void
    {
        $flota = ParametroFlota::query()->first();

        foreach (DB::table('componentes_costo')->get() as $componente) {
            [$nombre, $tasa] = self::TASAS_DE_LA_HOJA[$componente->nombre] ?? [
                $componente->nombre,
                $flota === null ? 0.0 : MetodoCosto::from($componente->metodo)
                    ->calculador()
                    ->derivar(json_decode($componente->entradas, true) ?? [], $flota)
                    ->tasa,
            ];

            DB::table('componentes_costo')
                ->where('id', $componente->id)
                ->update(['nombre' => $nombre, 'tasa' => round($tasa, 4)]);
        }

        $nombre = 'Otros gastos variables (peajes, viáticos y otros)';

        if (DB::table('componentes_costo')->where('nombre', $nombre)->doesntExist()) {
            DB::table('componentes_costo')->insert([
                'nombre' => $nombre,
                'tipo' => 'variable_km',
                'naturaleza' => 'directo',
                'metodo' => 'manual',
                'tasa' => 0.59,
                'entradas' => json_encode([]),
                'orden' => (int) DB::table('componentes_costo')->max('orden') + 1,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Las cotizaciones ya emitidas mantienen su precio. Los montos del tramo
     * que se cargaban sueltos pasan al desglose como una línea más por
     * kilómetro, y los totales se reparten entre fijos y variables.
     */
    private function reescribirCotizaciones(): void
    {
        foreach (DB::table('cotizaciones')->get() as $cotizacion) {
            $desglose = json_decode($cotizacion->desglose, true) ?? [];
            $lineas = $desglose['componentes'] ?? [];

            foreach ($desglose['ruta'] ?? [] as $ruta) {
                if ((float) $ruta['importe'] <= 0.0) {
                    continue;
                }

                $lineas[] = [
                    'nombre' => $ruta['nombre'],
                    'tipo' => 'variable_km',
                    'naturaleza' => 'directo',
                    'tasa' => $cotizacion->km > 0 ? round($ruta['importe'] / $cotizacion->km, 4) : 0.0,
                    'importe' => (float) $ruta['importe'],
                ];
            }

            $fijo = 0.0;
            $variable = 0.0;

            foreach ($lineas as $linea) {
                if ($linea['tipo'] === 'fijo_dia') {
                    $fijo += (float) $linea['importe'];
                } else {
                    $variable += (float) $linea['importe'];
                }
            }

            DB::table('cotizaciones')->where('id', $cotizacion->id)->update([
                'desglose' => json_encode(['componentes' => array_values($lineas)]),
                'total_fijo' => round($fijo, 2),
                'total_variable' => round($variable, 2),
            ]);
        }
    }
};
