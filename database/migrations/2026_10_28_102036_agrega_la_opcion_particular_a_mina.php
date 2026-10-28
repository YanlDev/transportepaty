<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * De regreso a la mina también sube carga de clientes particulares
     * (Metso y otros proveedores de San Rafael): se le factura al cliente,
     * no a Minsur. Va justo después de los materiales de Ransa.
     */
    public function up(): void
    {
        if (DB::table('opciones_tramo')->where('nombre', 'Particular a mina')->exists()) {
            return;
        }

        $materiales = DB::table('opciones_tramo')->where('nombre', 'Materiales a mina')->value('orden') ?? 7;

        DB::table('opciones_tramo')->where('orden', '>', $materiales)->increment('orden');

        DB::table('opciones_tramo')->insert([
            'nombre' => 'Particular a mina',
            'descripcion' => 'Lima → Mina (cliente particular, p. ej. Metso)',
            'sentido' => 'regreso',
            'paradas' => json_encode([['zona_origen' => 'lima', 'zona_destino' => 'mina', 'tipo_carga' => 'particular']]),
            'pide_destino' => false,
            'activa' => true,
            'orden' => $materiales + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('opciones_tramo')->where('nombre', 'Particular a mina')->delete();
    }
};
