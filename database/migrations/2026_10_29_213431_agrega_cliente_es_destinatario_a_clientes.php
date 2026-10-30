<?php

use App\Models\Cliente;
use App\Models\Factura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remitentes cuyo cliente real es el destinatario. Porcelanato Latino
     * despacha desde Chilca a tiendas de Juliaca, y el flete se le factura a
     * cada tienda, no a Porcelanato: con el remitente como cliente, los
     * reportes juntaban bajo «Porcelanato» lo que se cobra a diez clientes.
     */
    private const RUC_PORCELANATO = '20609175789';

    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('cliente_es_destinatario')->default(false)->after('recurrente');
        });

        $porcelanato = Cliente::query()->where('ruc', self::RUC_PORCELANATO)->first();

        if ($porcelanato !== null) {
            $porcelanato->update(['cliente_es_destinatario' => true]);
            $porcelanato->reasignarClienteDeSusViajes();
        }

        $this->corregirFacturadoA();
    }

    /**
     * Las facturas cargadas a mano copiaron el «Facturado a» del cliente del
     * viaje, que hasta hoy era Porcelanato: E001-14413 dice DECORA HOGAR y
     * E001-14461 CERAMIC'S IMPORT en el PDF, pero quedaron a nombre de
     * Porcelanato. Pasan al cliente que ahora tiene su viaje. Las subidas en
     * PDF traen el impreso y no se tocan.
     */
    private function corregirFacturadoA(): void
    {
        Factura::query()
            ->where('desde_pdf', false)
            ->where('cliente_ruc', self::RUC_PORCELANATO)
            ->with('viajes:viajes.id,cliente,cliente_ruc,remitente_ruc')
            ->each(function (Factura $factura): void {
                $viaje = $factura->viajes->sortBy('id')->first();

                if ($viaje === null || $viaje->remitente_ruc !== self::RUC_PORCELANATO || $viaje->cliente_ruc === self::RUC_PORCELANATO) {
                    return;
                }

                $factura->forceFill([
                    'cliente_razon_social' => $viaje->cliente,
                    'cliente_ruc' => $viaje->cliente_ruc,
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        $porcelanato = Cliente::query()->where('ruc', self::RUC_PORCELANATO)->first();

        if ($porcelanato !== null) {
            $porcelanato->update(['cliente_es_destinatario' => false]);
            $porcelanato->reasignarClienteDeSusViajes();
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('cliente_es_destinatario');
        });
    }
};
