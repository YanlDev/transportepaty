<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            // Null significa «todavía sin facturar», que es el estado inicial
            // de todo lo que ya está importado. Al borrar una factura los
            // viajes vuelven a ese estado en vez de irse con ella.
            $table->foreignId('factura_id')->nullable()->after('observaciones')
                ->constrained('facturas')->nullOnDelete();

            $table->index('factura_id');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropForeign(['factura_id']);
            $table->dropColumn('factura_id');
        });
    }
};
