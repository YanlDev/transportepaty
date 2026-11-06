<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada combustible autorizado dice a qué tramo del ticket va (ida o
     * vuelta) en vez de repartirse solo, y se anula con motivo en vez de
     * borrarse. Lo que pasa del tope de la tarjeta lleva su motivo. Después
     * se registra el despacho real (lo que dice el voucher del grifo): los
     * galones que de verdad se cargaron, a qué precio y con qué kilometraje.
     * El «tanque lleno» desaparece de la pizarra y de los registros.
     */
    public function up(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->dropColumn('tanque_lleno');
            $table->string('sentido', 10)->nullable()->after('vuelta_id');
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->string('motivo_exceso', 500)->nullable();
            $table->decimal('galones_despachados', 8, 2)->nullable();
            $table->unsignedInteger('kilometraje')->nullable();
            $table->date('despachado_el')->nullable();
            $table->foreignId('despacho_por')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('lineas_regla_abastecimiento', function (Blueprint $table) {
            $table->dropColumn('tanque_lleno');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_regla_abastecimiento', function (Blueprint $table) {
            $table->boolean('tanque_lleno')->default(false);
        });

        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropConstrainedForeignId('despacho_por');
            $table->dropColumn(['sentido', 'anulado_at', 'motivo_anulacion', 'motivo_exceso', 'galones_despachados', 'kilometraje', 'despachado_el']);
            $table->boolean('tanque_lleno')->default(false);
        });
    }
};
