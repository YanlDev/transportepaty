<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién deposita la detracción. Los clientes chicos de Juliaca le pagan a
     * la empresa el total de la factura y es la empresa la que deposita la
     * detracción en el Banco de la Nación: el neto que se le cobra al cliente
     * es el total, y la detracción deja de ser una deuda del cliente para ser
     * un pendiente de la empresa.
     *
     * Las facturas subidas en PDF ya lo dicen: su «neto pendiente de pago»
     * impreso es el total. Esas se marcan acá.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('detraccion_por_empresa')->default(false)->after('detraccion_impresa');
        });

        DB::table('facturas')
            ->where('desde_pdf', true)
            ->where('detraccion', '>', 0)
            ->whereColumn('neto', 'total')
            ->update(['detraccion_por_empresa' => true]);
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('detraccion_por_empresa');
        });
    }
};
