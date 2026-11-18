<?php

use App\Models\Ruta;
use App\Services\TablaDeTramos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Particular a mina» (JS Industrial, Administración de Empresas…) deja de
 * leer la tabla de Ransa: tiene su propia fila Lima → Mina para particular,
 * por ahora con los mismos galones. Así se puede cambiar una sin tocar la
 * otra. Ningún tope cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        $general = DB::table('galones_por_tramo')
            ->where('zona_origen', 'lima')->where('zona_destino', 'mina')->whereNull('tipo_carga')
            ->first();

        $yaEsta = DB::table('galones_por_tramo')
            ->where('zona_origen', 'lima')->where('zona_destino', 'mina')->where('tipo_carga', 'particular')
            ->exists();

        if ($general === null || $yaEsta) {
            return;
        }

        DB::table('galones_por_tramo')->insert([
            'zona_origen' => 'lima',
            'zona_destino' => 'mina',
            'tipo_carga' => 'particular',
            'galones_cargado' => $general->galones_cargado,
            'galones_vacio' => null,
            'bandas' => $general->bandas,
            'nota' => 'Particular a mina: por ahora los mismos galones que Ransa. Alcanza hasta Juliaca.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TablaDeTramos::class)->olvidar();

        // Mismas bandas: sus variantes conservan los ids.
        Ruta::query()->where('nombre', 'Particular a mina')->where('por_tramos', true)->first()?->sincronizarVariantesConLaTabla();
    }

    public function down(): void
    {
        DB::table('galones_por_tramo')
            ->where('zona_origen', 'lima')->where('zona_destino', 'mina')->where('tipo_carga', 'particular')
            ->delete();
    }
};
