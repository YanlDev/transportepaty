<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La mercadería se recibe cuando llega (el stock entra con los precios
     * del pedido) y el comprobante se completa después. Y una emergencia en
     * ruta puede ir directo a la unidad: al aprobarse entra y sale sola.
     */
    public function up(): void
    {
        Schema::table('ingresos_almacen', function (Blueprint $table) {
            $table->boolean('comprobante_pendiente')->default(false)->after('tipo_comprobante');
        });

        Schema::table('vales_compra', function (Blueprint $table) {
            $table->boolean('directo_a_unidad')->default(false)->after('permite_parciales');
        });
    }

    public function down(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn('directo_a_unidad');
        });

        Schema::table('ingresos_almacen', function (Blueprint $table) {
            $table->dropColumn('comprobante_pendiente');
        });
    }
};
