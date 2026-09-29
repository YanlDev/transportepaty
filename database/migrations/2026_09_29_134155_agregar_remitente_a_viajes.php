<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El remitente de la GR, aparte del cliente. No siempre son el mismo: en las
 * GR de Ajeper o Embotelladora Caral el cliente de Paty —a quien se cobra— es
 * Crisar, que subcontrata o paga el flete, pero la GR la emitió el remitente.
 * Hasta ahora el importador se quedaba solo con el cliente y el remitente se
 * perdía.
 *
 * Se llenan con `transpaty:completar-remitentes`, que vuelve a leer el PDF de
 * cada viaje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->string('remitente')->nullable()->after('cliente_ruc');
            $table->string('remitente_ruc', 11)->nullable()->after('remitente');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->dropColumn(['remitente', 'remitente_ruc']);
        });
    }
};
