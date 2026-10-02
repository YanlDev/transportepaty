<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hay GR que se emiten pero no se cobran: una cajita de 0.2 TNE que viaja
     * con la carga grande, una cortesía. Sin marca, quedan para siempre en
     * «por facturar» e inflan lo pendiente. No es anularla —ante SUNAT la GR
     * sigue valiendo y el viaje cuenta en la operación—, solo sale de la
     * cobranza.
     */
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->timestamp('no_facturable_at')->nullable()->after('gr_fisica_recibida_at');
            $table->string('motivo_no_facturable')->nullable()->after('no_facturable_at');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropColumn(['no_facturable_at', 'motivo_no_facturable']);
        });
    }
};
