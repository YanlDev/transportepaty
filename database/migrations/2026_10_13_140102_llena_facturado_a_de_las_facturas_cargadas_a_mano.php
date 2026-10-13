<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «Facturado a» de las facturas cargadas a mano, que no tienen PDF de
     * donde leerlo: se toma el cliente de la primera GR que cubren. Es un
     * supuesto —casi siempre se le factura al cliente de la GR— y se corrige
     * en la celda si no lo es. Las que ya tienen cliente (las del PDF) no se
     * tocan.
     */
    public function up(): void
    {
        $facturaIds = DB::table('facturas')->whereNull('cliente_razon_social')->pluck('id');

        foreach ($facturaIds as $facturaId) {
            $viaje = DB::table('factura_viaje')
                ->join('viajes', 'viajes.id', '=', 'factura_viaje.viaje_id')
                ->where('factura_viaje.factura_id', $facturaId)
                ->orderBy('viajes.id')
                ->first(['viajes.cliente', 'viajes.cliente_ruc']);

            if ($viaje === null || blank($viaje->cliente)) {
                continue;
            }

            DB::table('facturas')->where('id', $facturaId)->update([
                'cliente_razon_social' => $viaje->cliente,
                'cliente_ruc' => $viaje->cliente_ruc,
            ]);
        }
    }

    /**
     * Sin vuelta atrás: ya no se distingue lo llenado acá de lo escrito a
     * mano después.
     */
    public function down(): void {}
};
