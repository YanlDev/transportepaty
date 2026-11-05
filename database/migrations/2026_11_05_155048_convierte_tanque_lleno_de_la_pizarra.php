<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «Tanque lleno» deja de existir: era ambiguo (cuánto es lleno depende de
     * cuánto le quedaba). Cada línea «full» pasa a ser una línea de galones
     * por definir, y su tarjeta queda con una nota para que alguien ponga
     * los galones de verdad.
     */
    public function up(): void
    {
        $reglas = DB::table('lineas_regla_abastecimiento')
            ->where('tanque_lleno', true)
            ->distinct()
            ->pluck('regla_abastecimiento_id');

        foreach ($reglas as $reglaId) {
            $nota = DB::table('reglas_abastecimiento')->where('id', $reglaId)->value('nota');
            $aviso = 'Tenía «tanque full»: falta poner los galones de esa línea.';

            DB::table('reglas_abastecimiento')->where('id', $reglaId)->update([
                'nota' => trim(($nota ? $nota."\n" : '').$aviso),
            ]);
        }

        DB::table('lineas_regla_abastecimiento')
            ->where('tanque_lleno', true)
            ->update(['tanque_lleno' => false, 'galones' => null, 'concepto' => 'Combustible']);

        DB::table('ajustes')->where('clave', 'galones_tanque_lleno')->delete();
    }

    public function down(): void
    {
        // Los galones nunca se pusieron: no hay nada que reponer.
    }
};
