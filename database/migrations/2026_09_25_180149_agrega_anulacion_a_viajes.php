<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una GR anulada ante SUNAT —por una placa mal escrita, un destino que hubo
 * que corregir— sigue siendo un documento emitido, así que no se borra: se
 * marca, y desde ahí deja de contar como viaje en todo el sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->timestamp('anulada_at')->nullable()->index();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_anulacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_at', 'motivo_anulacion']);
        });
    }
};
