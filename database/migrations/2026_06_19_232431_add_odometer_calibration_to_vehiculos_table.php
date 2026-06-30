<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table): void {
            // Última lectura del odómetro del GPS ya aplicada al kilometraje real.
            $table->unsignedInteger('gps_km_base')->nullable()->after('imei');
            // Cuándo se calibró por última vez el odómetro real contra el GPS.
            $table->timestamp('km_calibrado_en')->nullable()->after('gps_km_base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table): void {
            $table->dropColumn(['gps_km_base', 'km_calibrado_en']);
        });
    }
};
