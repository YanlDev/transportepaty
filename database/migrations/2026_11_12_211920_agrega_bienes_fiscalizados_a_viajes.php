<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La GRE de Bienes Fiscalizados (serie G0xx) no trae peso: hay que pedirlo a
 * mano, en toneladas, después de importarla. La marca dice qué GR son de ese
 * tipo, y con peso 0 sale como «Falta peso».
 *
 * Las que ya estaban importadas se reconocen por lo mismo que las distingue:
 * peso 0 y serie que empieza con G (las GRE normales son EGxx).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->boolean('bienes_fiscalizados')->default(false)->after('unidad_peso');
        });

        DB::table('viajes')
            ->where('peso', 0)
            ->where('numero_gr', 'like', 'G%')
            ->update(['bienes_fiscalizados' => true]);
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->dropColumn('bienes_fiscalizados');
        });
    }
};
