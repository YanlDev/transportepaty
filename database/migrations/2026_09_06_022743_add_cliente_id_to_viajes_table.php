<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo criterio que `tracto_id`, `carreta_id` y `conductor_id`: la GR sigue
 * guardando el nombre y el RUC tal como vinieron, y este FK se llena solo
 * cuando el RUC matchea contra el padrón. Un cliente que todavía no está
 * dado de alta deja el viaje con el texto crudo y el FK en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->foreignId('cliente_id')
                ->nullable()
                ->after('cliente_ruc')
                ->constrained('clientes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
