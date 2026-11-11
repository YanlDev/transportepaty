<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las rutas con tanque full arrastraban de la pizarra la nota «Tenía
 * «tanque full»: falta poner los galones de esa línea.». Ya no aplica: el
 * full va sin galones a propósito y el tope está en «Hasta Juliaca».
 */
return new class extends Migration
{
    private const AVISO = 'Tenía «tanque full»: falta poner los galones de esa línea.';

    public function up(): void
    {
        DB::table('rutas')
            ->where('nota', 'like', '%'.self::AVISO.'%')
            ->get(['id', 'nota'])
            ->each(function (object $ruta): void {
                $nota = trim(str_replace(self::AVISO, '', (string) $ruta->nota));

                DB::table('rutas')->where('id', $ruta->id)->update([
                    'nota' => $nota === '' ? null : $nota,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Era un aviso viejo: no se repone.
    }
};
