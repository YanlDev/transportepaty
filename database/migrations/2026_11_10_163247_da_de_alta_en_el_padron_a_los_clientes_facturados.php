<?php

use App\Models\Cliente;
use App\Services\ImportadorViaje;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los clientes de la casa son a quienes se les factura. Hasta ahora el padrón
 * se llenaba a mano y había facturas emitidas a RUC que no estaban en él: el
 * mismo cliente salía con dos o tres nombres según quién lo escribió.
 *
 * Da de alta cada RUC facturado que falta, con la razón social de su factura
 * más reciente (la del PDF, si tiene) y un alias sugerido, y enlaza las GR que
 * ya lo nombraban. No toca los clientes que ya estaban ni ninguna factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        $faltantes = DB::table('facturas')
            ->whereNotNull('cliente_ruc')
            ->whereNotNull('cliente_razon_social')
            ->whereNotIn('cliente_ruc', DB::table('clientes')->select('ruc'))
            ->orderByDesc('desde_pdf')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get(['cliente_ruc', 'cliente_razon_social'])
            ->unique('cliente_ruc');

        foreach ($faltantes as $facturado) {
            $clienteId = DB::table('clientes')->insertGetId([
                'ruc' => $facturado->cliente_ruc,
                'razon_social' => ImportadorViaje::normalizarRazonSocial($facturado->cliente_razon_social),
                'alias' => Cliente::aliasSugerido($facturado->cliente_razon_social),
                'recurrente' => false,
                'activo' => true,
                'notas' => 'Alta automática: se le emitió una factura. Revisar el alias y completar el contacto.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('viajes')
                ->where('cliente_ruc', $facturado->cliente_ruc)
                ->whereNull('cliente_id')
                ->update(['cliente_id' => $clienteId]);
        }
    }

    /**
     * Sin vuelta atrás: ya no se distinguen de los clientes dados de alta a
     * mano, y borrarlos soltaría las GR y cotizaciones que los nombren.
     */
    public function down(): void {}
};
