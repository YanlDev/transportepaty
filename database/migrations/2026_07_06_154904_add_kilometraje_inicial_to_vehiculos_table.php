<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Odómetro del vehículo al momento del alta. Determina si un servicio
     * único (p. ej. "inspección inicial 1000 km") aplica: sólo cuando la unidad
     * ingresó a la flota nueva, por debajo del km objetivo.
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->unsignedInteger('kilometraje_inicial')->nullable()->after('kilometraje');
        });

        // Backfill: para las unidades ya registradas usamos el mayor odómetro
        // conocido (kilometraje, base GPS, cargas y mantenimientos) como estima
        // de "no es nueva". El kilometraje de las unidades con GPS puede estar
        // transitoriamente en 0 mientras no sincroniza, así que no basta ese
        // campo; el histórico de cargas conserva la lectura real. Se resuelve en
        // PHP para ser portable entre SQLite (tests) y PostgreSQL (producción).
        DB::table('vehiculos')->orderBy('id')->get(['id', 'kilometraje', 'gps_km_base'])
            ->each(function (object $vehiculo): void {
                $maxCarga = DB::table('cargas_combustible')->where('vehiculo_id', $vehiculo->id)->max('odometro') ?? 0;
                $maxMantenimiento = DB::table('mantenimientos')->where('vehiculo_id', $vehiculo->id)->max('odometro') ?? 0;

                DB::table('vehiculos')->where('id', $vehiculo->id)->update([
                    'kilometraje_inicial' => max(
                        (int) $vehiculo->kilometraje,
                        (int) ($vehiculo->gps_km_base ?? 0),
                        (int) $maxCarga,
                        (int) $maxMantenimiento,
                    ),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('kilometraje_inicial');
        });
    }
};
