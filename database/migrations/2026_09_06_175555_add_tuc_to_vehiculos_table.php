<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            // Número de TUCE o Certificado de Habilitación Vehicular. La GRE lo
            // exige por cada placa declarada (tracto y carreta).
            $table->string('tuc')->nullable()->after('placa');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('tuc');
        });
    }
};
