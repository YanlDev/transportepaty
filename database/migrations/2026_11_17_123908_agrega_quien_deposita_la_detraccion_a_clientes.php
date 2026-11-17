<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los clientes a los que la empresa les deposita la detracción (pagan el
     * total): la lista que llenó la gerencia el 17-11-2026, más Corporación
     * R & R Flores y Transsonia.
     */
    private const DEPOSITA_LA_EMPRESA = [
        '20600812913', '20601239079', '20610657789', '20601833019', '20406507336', '10024024194', '10310435223',
        '20448699341', '20607433021', '20447751152', '20604950792', '20602182984', '20447882923', '20602560865',
        '10046531413', '20610177370', '10411108428', '20608123238', '20518023731', '10024263741', '20100202396',
        '10729523319', '20523324609', '20447996422', '20478230720', '20610384049', '20604259241', '20609175789',
        '20611963255', '20564110974',
    ];

    /** Facturas de esos clientes que se registraron sin RUC (Hatun Wasi y Leuman). */
    private const FACTURAS_SIN_RUC = ['E001-14677', 'E001-14522'];

    /**
     * Quién deposita la detracción pasa a ser un dato del cliente: cada
     * factura nueva lo toma de ahí. Las facturas que ya existen de esos
     * clientes se corrigen: el cliente pagó (o debe) el total, y la
     * detracción es un depósito pendiente de la empresa.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('detraccion_por_empresa')->default(false);
        });

        DB::table('clientes')->whereIn('ruc', self::DEPOSITA_LA_EMPRESA)->update(['detraccion_por_empresa' => true]);

        DB::table('facturas')
            ->where(fn ($query) => $query->whereIn('cliente_ruc', self::DEPOSITA_LA_EMPRESA)->orWhereIn('numero', self::FACTURAS_SIN_RUC))
            ->where('detraccion_por_empresa', false)
            ->where('detraccion', '>', 0)
            ->whereNotNull('total')
            // La detracción se deposita en soles: una factura en dólares de
            // estos clientes se revisa a mano.
            ->where('moneda', 'PEN')
            ->update(['detraccion_por_empresa' => true, 'neto' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('detraccion_por_empresa');
        });
    }
};
