<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // El nombre con el que se lo conoce en la calle, que no siempre
            // es la razón social ni el alias corto de las tablas.
            $table->string('nombre_comercial')->nullable()->after('alias');
            $table->string('direccion')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['nombre_comercial', 'direccion']);
        });
    }
};
