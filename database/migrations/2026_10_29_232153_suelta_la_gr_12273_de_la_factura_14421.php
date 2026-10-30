<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La E001-14421 de San Lorenzo cita en su PDF las GR 12286 y 12287, pero
     * quedó asociada además a la 12273, que ya se cobró con su propia factura
     * (E001-14420). Con las dos, la 12273 figuraba cobrada dos veces.
     */
    public function up(): void
    {
        $facturaId = DB::table('facturas')->where('numero', 'E001-14421')->value('id');
        $viajeId = DB::table('viajes')->where('numero_gr', 'EG03-00012273')->value('id');

        if ($facturaId === null || $viajeId === null) {
            return;
        }

        DB::table('factura_viaje')
            ->where('factura_id', $facturaId)
            ->where('viaje_id', $viajeId)
            ->delete();
    }

    public function down(): void
    {
        // No se vuelve a asociar: la asociación era un error.
    }
};
