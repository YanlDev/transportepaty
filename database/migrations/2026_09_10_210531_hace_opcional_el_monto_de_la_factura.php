<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La cobranza se llena en línea, celda por celda, igual que en la hoja de
     * cálculo de la que viene: el número de factura se conoce al emitirla y el
     * monto puede cargarse después. Exigir el monto de entrada obligaba a
     * inventar una cifra para poder registrar el número, que es peor que
     * dejarlo vacío y verlo vacío.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('monto', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('monto', 12, 2)->nullable(false)->change();
        });
    }
};
