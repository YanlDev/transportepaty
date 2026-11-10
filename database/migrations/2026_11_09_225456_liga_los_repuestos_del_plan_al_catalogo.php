<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que lleva cada nivel del plan deja de ser texto suelto: cada ítem
     * apunta a un artículo del almacén, o se marca como servicio (una
     * calibración, la mano de obra), que no sale del almacén.
     *
     * Las filas se conservan tal cual. Se marca como servicio lo que ya lo
     * decía (unidad «serv») o no tenía cantidad, y se liga solo lo que
     * coincide sin duda: el mismo número de parte en un único artículo. El
     * resto se liga a mano desde el plan de cada marca.
     */
    public function up(): void
    {
        Schema::table('repuestos_servicio', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('servicio_mantenimiento_id')
                ->constrained('articulos')->restrictOnDelete();
            $table->boolean('es_servicio')->default(false)->after('articulo_id');
        });

        DB::table('repuestos_servicio')
            ->where(fn ($query) => $query->where('unidad', 'serv')->orWhereNull('cantidad'))
            ->update(['es_servicio' => true]);

        $articulos = DB::table('articulos')
            ->whereNotNull('numero_parte')
            ->get(['id', 'numero_parte'])
            ->groupBy(fn (object $articulo): string => mb_strtoupper(trim((string) $articulo->numero_parte)));

        $repuestos = DB::table('repuestos_servicio')
            ->where('es_servicio', false)
            ->whereNotNull('codigo')
            ->get(['id', 'codigo']);

        foreach ($repuestos as $repuesto) {
            $coinciden = $articulos->get(mb_strtoupper(trim((string) $repuesto->codigo)));

            if ($coinciden !== null && $coinciden->count() === 1) {
                DB::table('repuestos_servicio')
                    ->where('id', $repuesto->id)
                    ->update(['articulo_id' => $coinciden->first()->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('repuestos_servicio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('articulo_id');
            $table->dropColumn('es_servicio');
        });
    }
};
