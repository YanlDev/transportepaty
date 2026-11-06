<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De qué paso del ticket salió la lectura («abastecimiento:12»,
     * «llegada:5», «salida:5»): al corregir ese km se corrige la misma
     * lectura en vez de sumar otra. Las anotadas a mano no tienen origen.
     */
    public function up(): void
    {
        Schema::table('lecturas_kilometraje', function (Blueprint $table) {
            $table->string('origen', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('lecturas_kilometraje', function (Blueprint $table) {
            $table->dropUnique(['origen']);
            $table->dropColumn('origen');
        });
    }
};
