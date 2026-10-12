<?php

use App\Enums\TipoCarga;
use App\Services\DesgloseFactura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si la detracción se redondea a soles enteros. Depende de cómo la emite
     * cada cliente: casi todas van redondeadas (336, 434, 1 940), pero las de
     * Minsur llevan céntimos (4 508.25). Por defecto se redondea.
     *
     * Las facturas de Minsur ya cargadas a mano se recalculan con céntimos;
     * las subidas en PDF no se tocan, porque traen la cifra impresa.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('detraccion_redondeada')->default(true)->after('detraccion');
        });

        $deMinsur = DB::table('facturas')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('factura_viaje')
                ->join('viajes', 'viajes.id', '=', 'factura_viaje.viaje_id')
                ->whereColumn('factura_viaje.factura_id', 'facturas.id')
                ->where('viajes.cliente_ruc', TipoCarga::RUC_MINSUR))
            ->get(['id', 'monto', 'total', 'tasa_igv', 'tasa_detraccion', 'desde_pdf']);

        foreach ($deMinsur as $factura) {
            $cambios = ['detraccion_redondeada' => false];

            if (! $factura->desde_pdf && $factura->monto !== null) {
                $cambios = [
                    ...$cambios,
                    ...DesgloseFactura::desdeValor((float) $factura->monto, (float) $factura->tasa_igv, (float) $factura->tasa_detraccion, 400, redondear: false),
                ];
            }

            DB::table('facturas')->where('id', $factura->id)->update($cambios);
        }
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('detraccion_redondeada');
        });
    }
};
