<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El dinero del combustible: a cuánto salió el galón en ese grifo y el
     * importe (galones × precio). Opcionales: lo ya registrado no tiene precio.
     */
    public function up(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->decimal('precio_galon', 7, 3)->nullable()->after('tanque_lleno');
            $table->decimal('importe', 10, 2)->nullable()->after('precio_galon');
        });
    }

    public function down(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->dropColumn(['precio_galon', 'importe']);
        });
    }
};
