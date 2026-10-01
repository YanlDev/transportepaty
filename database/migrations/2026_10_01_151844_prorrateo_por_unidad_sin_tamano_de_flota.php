<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los gastos de estructura dejan de cargarse como el total de una flota que
 * después se divide entre sus unidades: se cargan por unidad. El tamaño de
 * flota que había sembrado (100) era el de la hoja de otra estructura, no el
 * de Paty, y cambiarlo no movía las tasas, solo confundía.
 *
 * Cada línea conserva lo que sugería: el monto por unidad es el total por la
 * dedicación, dividido entre la flota que tenía.
 */
return new class extends Migration
{
    public function up(): void
    {
        $flota = (int) (DB::table('parametros_flota')->value('tamano_flota') ?: 1);

        DB::table('componentes_costo')
            ->where('metodo', 'prorrateo_anual')
            ->get(['id', 'entradas'])
            ->each(function (object $componente) use ($flota): void {
                $entradas = json_decode((string) $componente->entradas, true) ?: [];
                $montoAnual = (float) ($entradas['monto_anual'] ?? 0);
                $dedicacion = (float) ($entradas['dedicacion_pct'] ?? 1);

                DB::table('componentes_costo')->where('id', $componente->id)->update([
                    'entradas' => json_encode([
                        'monto_anual_unidad' => round($montoAnual * $dedicacion / $flota, 2),
                    ]),
                ]);
            });

        Schema::table('parametros_flota', function (Blueprint $table): void {
            $table->dropColumn('tamano_flota');
        });
    }

    public function down(): void
    {
        Schema::table('parametros_flota', function (Blueprint $table): void {
            $table->unsignedInteger('tamano_flota')->default(1)->after('id');
        });

        DB::table('componentes_costo')
            ->where('metodo', 'prorrateo_anual')
            ->get(['id', 'entradas'])
            ->each(function (object $componente): void {
                $entradas = json_decode((string) $componente->entradas, true) ?: [];

                DB::table('componentes_costo')->where('id', $componente->id)->update([
                    'entradas' => json_encode([
                        'monto_anual' => (float) ($entradas['monto_anual_unidad'] ?? 0),
                        'dedicacion_pct' => 1,
                    ]),
                ]);
            });
    }
};
