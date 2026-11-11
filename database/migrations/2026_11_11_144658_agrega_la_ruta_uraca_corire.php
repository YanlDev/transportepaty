<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El arroz de Uraca (Corire, Castilla): un complemento de 40 gal para la
 * unidad que descarga en Arequipa y se desvía a cargar ahí antes de subir a
 * Juliaca. Hasta ahora se le ponía «Camaná (arroz)», que es otro desvío.
 */
return new class extends Migration
{
    private const NOMBRE = 'Uraca (Corire)';

    public function up(): void
    {
        if (DB::table('rutas')->where('nombre', self::NOMBRE)->exists()) {
            return;
        }

        $rutaId = DB::table('rutas')->insertGetId([
            'nombre' => self::NOMBRE,
            'descripcion' => 'Arroz de Uraca (Corire, Castilla)',
            'sentido' => null,
            'es_complemento' => true,
            'paradas' => json_encode([]),
            'pide_destino' => false,
            'color' => 'gris',
            'activa' => true,
            'orden' => (int) DB::table('rutas')->max('orden') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lineas_ruta')->insert([
            'ruta_id' => $rutaId,
            'tipo' => 'combustible',
            'concepto' => 'Uraca',
            'galones' => 40,
            'tanque_lleno' => false,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $rutaId = DB::table('rutas')->where('nombre', self::NOMBRE)->value('id');

        DB::table('lineas_ruta')->where('ruta_id', $rutaId)->delete();
        DB::table('rutas')->where('id', $rutaId)->delete();
    }
};
