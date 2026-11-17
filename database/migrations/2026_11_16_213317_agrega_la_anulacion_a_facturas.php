<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una factura ya no se borra: se anula. Queda guardada con el motivo,
     * quién la anuló y cuándo, y con las GR que cobraba (para poder
     * restaurarla), pero deja de contar en la cobranza.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->timestamp('anulada_at')->nullable()->index();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->json('viajes_al_anular')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_at', 'motivo_anulacion', 'viajes_al_anular']);
        });
    }
};
