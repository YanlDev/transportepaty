<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una factura ya podía cubrir varias GR; ahora una GR también puede tener
     * varias facturas —el flete por un lado y la estadía por otro, o un
     * cliente que pide partir el cobro—. `viajes.factura_id` solo admitía una,
     * así que el enlace pasa a una tabla propia.
     *
     * Los enlaces existentes se copian antes de soltar la columna: lo que ya
     * estaba facturado sigue facturado.
     */
    public function up(): void
    {
        Schema::create('factura_viaje', function (Blueprint $table) {
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('viaje_id')->constrained('viajes')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['factura_id', 'viaje_id']);
            $table->index('viaje_id');
        });

        DB::table('factura_viaje')->insertUsing(
            ['factura_id', 'viaje_id', 'created_at', 'updated_at'],
            DB::table('viajes')
                ->whereNotNull('factura_id')
                ->select(['factura_id', 'id', 'updated_at', 'updated_at']),
        );

        Schema::table('viajes', function (Blueprint $table) {
            $table->dropForeign(['factura_id']);
            $table->dropIndex(['factura_id']);
            $table->dropColumn('factura_id');
        });
    }

    /**
     * De vuelta a una factura por viaje. Si una GR quedó con varias, se queda
     * con la más antigua: es la única forma de caber en una columna.
     */
    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->foreignId('factura_id')->nullable()->after('observaciones')
                ->constrained('facturas')->nullOnDelete();

            $table->index('factura_id');
        });

        DB::table('factura_viaje')
            ->selectRaw('viaje_id, min(factura_id) as factura_id')
            ->groupBy('viaje_id')
            ->get()
            ->each(fn (object $enlace) => DB::table('viajes')
                ->where('id', $enlace->viaje_id)
                ->update(['factura_id' => $enlace->factura_id]));

        Schema::dropIfExists('factura_viaje');
    }
};
