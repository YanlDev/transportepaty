<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Al salir de la mina no se sabe si en Pisco cargará metálico: el
     * metálico al Callao deja de ir pegado al concentrado y pasa a ser un
     * tramo de ida aparte (Pisco → Callao), que se agrega cuando la ida ya
     * llegó a Pisco. La opción combinada sale.
     */
    public function up(): void
    {
        $combinada = DB::table('opciones_tramo')->where('nombre', 'Concentrado + metálico al Callao')->first();

        if ($combinada === null) {
            return;
        }

        DB::table('opciones_tramo')->where('id', $combinada->id)->update([
            'nombre' => 'Metálico al Callao',
            'descripcion' => 'Pisco → Callao (después del concentrado)',
            'paradas' => json_encode([['zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'metalico']]),
            'pide_destino' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('opciones_tramo')->where('nombre', 'Metálico al Callao')->update([
            'nombre' => 'Concentrado + metálico al Callao',
            'descripcion' => 'Mina → Pisco → Callao',
            'paradas' => json_encode([
                ['zona_origen' => 'mina', 'zona_destino' => 'pisco', 'tipo_carga' => 'concentrado'],
                ['zona_origen' => 'pisco', 'zona_destino' => 'lima', 'tipo_carga' => 'metalico'],
            ]),
            'updated_at' => now(),
        ]);
    }
};
