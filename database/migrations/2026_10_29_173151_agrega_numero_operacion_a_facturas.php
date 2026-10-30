<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El número de operación con que entró el neto a la cuenta de la empresa:
     * lo que hace falta para encontrar el depósito en el estado de cuenta.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('numero_operacion', 30)->nullable()->after('cuenta_bancaria_id');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('numero_operacion');
        });
    }
};
