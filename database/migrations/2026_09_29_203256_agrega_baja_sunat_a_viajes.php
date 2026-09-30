<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo y por qué se dio de baja la GR en SUNAT desde Transpaty. Es aparte de
 * `anulada_at`: una GR se puede anular solo en Transpaty (ya se dio de baja a
 * mano en SOL), pero una dada de baja en SUNAT no se puede reactivar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->timestamp('baja_sunat_at')->nullable();
            $table->string('motivo_baja_sunat', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->dropColumn(['baja_sunat_at', 'motivo_baja_sunat']);
        });
    }
};
