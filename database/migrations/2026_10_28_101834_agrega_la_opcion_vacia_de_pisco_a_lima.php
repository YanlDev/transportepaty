<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Después del concentrado, la unidad puede seguir vacía de Pisco a Lima
     * a buscar carga de regreso. Va justo después del metálico al Callao.
     */
    public function up(): void
    {
        if (DB::table('opciones_tramo')->where('nombre', 'Vacía de Pisco a Lima')->exists()) {
            return;
        }

        $metalico = DB::table('opciones_tramo')->where('nombre', 'Metálico al Callao')->value('orden') ?? 2;

        DB::table('opciones_tramo')->where('orden', '>', $metalico)->increment('orden');

        DB::table('opciones_tramo')->insert([
            'nombre' => 'Vacía de Pisco a Lima',
            'descripcion' => 'Pisco ⇢ Lima (después del concentrado)',
            'sentido' => 'ida',
            'paradas' => json_encode([['zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'vacio']]),
            'pide_destino' => false,
            'activa' => true,
            'orden' => $metalico + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('opciones_tramo')->where('nombre', 'Vacía de Pisco a Lima')->delete();
    }
};
