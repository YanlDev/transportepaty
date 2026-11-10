<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El Registro para el Control de Bienes Fiscalizados (IQBF) de SUNAT nombra
 * las placas y los conductores con los que la empresa puede trasladar insumos
 * químicos. Solo esas unidades y esos choferes pueden ir en una GR de bienes
 * fiscalizados.
 *
 * Del conductor SUNAT publica la fecha de alta; de la unidad, no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table): void {
            $table->boolean('iqbf')->default(false)->after('tuc');
        });

        Schema::table('conductores', function (Blueprint $table): void {
            $table->boolean('iqbf')->default(false)->after('externo');
            $table->date('iqbf_desde')->nullable()->after('iqbf');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table): void {
            $table->dropColumn('iqbf');
        });

        Schema::table('conductores', function (Blueprint $table): void {
            $table->dropColumn(['iqbf', 'iqbf_desde']);
        });
    }
};
