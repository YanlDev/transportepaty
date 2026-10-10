<?php

use App\Services\DesgloseFactura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El flete facturado, descompuesto como lo imprime SUNAT: valor (que sigue
     * siendo `monto`), IGV, total, detracción y neto. Se guardan las cifras y
     * las tasas con que se emitió, no solo el valor: una factura emitida es un
     * hecho y no debe cambiar si mañana cambia una tasa.
     *
     * El cobro se parte en dos porque el cliente paga en dos depósitos: el
     * neto a la cuenta de la empresa (`fecha_pago` y `cuenta_bancaria_id`, que
     * ya existían) y la detracción al Banco de la Nación.
     *
     * Las facturas que ya tenían monto se completan a partir de él: hasta
     * ahora `monto` guardaba el valor sin IGV.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('tasa_igv', 5, 4)->default(0.18)->after('monto');
            $table->decimal('igv', 12, 2)->nullable()->after('tasa_igv');
            $table->decimal('total', 12, 2)->nullable()->after('igv');
            $table->decimal('tasa_detraccion', 5, 4)->default(0.04)->after('total');
            $table->decimal('detraccion', 12, 2)->nullable()->after('tasa_detraccion');
            $table->decimal('neto', 12, 2)->nullable()->after('detraccion');
            $table->date('fecha_detraccion')->nullable()->after('cuenta_bancaria_id');
            $table->string('constancia_detraccion', 30)->nullable()->after('fecha_detraccion');
        });

        DB::table('facturas')
            ->whereNotNull('monto')
            ->orderBy('id')
            ->each(function (object $factura): void {
                DB::table('facturas')
                    ->where('id', $factura->id)
                    ->update(DesgloseFactura::desdeValor((float) $factura->monto, 0.18, 0.04, 400));
            });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn([
                'tasa_igv',
                'igv',
                'total',
                'tasa_detraccion',
                'detraccion',
                'neto',
                'fecha_detraccion',
                'constancia_detraccion',
            ]);
        });
    }
};
