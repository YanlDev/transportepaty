<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que se lee del PDF de la factura al subirla.
     *
     * - `desde_pdf`: las cifras son las impresas y no se recalculan. La
     *   detracción impresa puede no ser el 4% del total (se calcula sobre el
     *   valor referencial cuando es mayor) y manda la de SUNAT.
     * - `fecha_vencimiento`: la de la cuota impresa. Vacía en las facturas
     *   cargadas a mano, que vencen a los 30 días de emitidas.
     * - `cliente_ruc` y `cliente_razon_social`: a quién se facturó, para
     *   proponer GR del mismo cliente cuando no se pudo asociar sola.
     * - `gr_citadas`: las GR-transportista que cita la factura. Las que no
     *   estaban en el sistema al subirla se asocian solas cuando llegan.
     * - `periodo_desde` y `periodo_hasta`: el rango de las facturas que no
     *   citan GR sino un período («del 25 de agosto al 14 de setiembre»).
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('desde_pdf')->default(false)->after('neto');
            $table->date('fecha_vencimiento')->nullable()->after('fecha_emision');
            $table->string('cliente_ruc', 11)->nullable()->index()->after('numero');
            $table->string('cliente_razon_social')->nullable()->after('cliente_ruc');
            $table->json('gr_citadas')->nullable()->after('observacion');
            $table->date('periodo_desde')->nullable()->after('gr_citadas');
            $table->date('periodo_hasta')->nullable()->after('periodo_desde');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex(['cliente_ruc']);
            $table->dropColumn([
                'desde_pdf',
                'fecha_vencimiento',
                'cliente_ruc',
                'cliente_razon_social',
                'gr_citadas',
                'periodo_desde',
                'periodo_hasta',
            ]);
        });
    }
};
