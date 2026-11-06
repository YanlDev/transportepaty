<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dónde cargó cuando no es un punto de la lista: un grifo de ruta en un
     * caso excepcional («Primax Camaná»). Va escrito a mano y el punto queda
     * vacío.
     */
    public function up(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->string('punto_libre', 80)->nullable()->after('punto_abastecimiento_id');
        });
    }

    public function down(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->dropColumn('punto_libre');
        });
    }
};
