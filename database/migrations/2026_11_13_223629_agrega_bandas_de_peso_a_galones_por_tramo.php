<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un tramo cargado puede gastar distinto según las toneladas (Lima → Mina:
 * de 110 a 190 gal). Sus bandas van en la misma fila de la tabla; con
 * bandas, el número único de «cargado» no se usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galones_por_tramo', function (Blueprint $table): void {
            $table->json('bandas')->nullable()->after('galones_vacio');
        });
    }

    public function down(): void
    {
        Schema::table('galones_por_tramo', function (Blueprint $table): void {
            $table->dropColumn('bandas');
        });
    }
};
