<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conductores externos: manejan para Paty pero no son de la casa. Se les
     * exige DNI y licencias, no el PIC de la mina.
     *
     * Se marcan los que la empresa confirmó el 2026-10-16, por licencia y no
     * por id ni por DNI: Quispe Quispe Ubaldo está dos veces en el padrón, y
     * en una de ellas el DNI se cargó con la licencia.
     */
    public function up(): void
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->boolean('externo')->default(false)->after('activo');
        });

        DB::table('conductores')
            ->whereIn(DB::raw('upper(trim(licencia))'), [
                'U71134737', // Mayta Challco Yone
                'U02411719', // Pineda Tito Lucio
                'U01269612', // Pacheco Cutipa Serafin
                'U45154573', // Quispe Quispe Ubaldo
            ])
            ->update(['externo' => true]);
    }

    public function down(): void
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->dropColumn('externo');
        });
    }
};
