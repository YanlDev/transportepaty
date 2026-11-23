<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La llanta existe desde que entra al almacén, no desde que se monta.
     *
     * - `llantas.detalle_ingreso_id`: la línea de la recepción con que entró.
     *   La recepción es su lote.
     * - `llantas.costo`: lo que costó, en soles, según esa recepción.
     * - `vehiculo_id`, `posicion` e `instalada_el` quedan vacíos mientras la
     *   llanta está en el almacén.
     * - El código es de la llanta y no se repite (sin distinguir mayúsculas).
     *
     * La categoría «Llantas» queda solo con llantas: los parches y demás
     * pasan a «Accesorios de llantas», y las llantas sin categoría entran.
     */
    public function up(): void
    {
        Schema::table('llantas', function (Blueprint $table) {
            $table->foreignId('detalle_ingreso_id')->nullable()->after('articulo_id')->constrained('detalles_ingreso')->restrictOnDelete();
            $table->decimal('costo', 12, 2)->nullable()->after('condicion');
            $table->unsignedBigInteger('vehiculo_id')->nullable()->change();
            $table->string('posicion', 4)->nullable()->change();
            $table->date('instalada_el')->nullable()->change();
        });

        DB::table('llantas')->where('codigo', '')->update(['codigo' => null]);

        // Los códigos repetidos de antes se distinguen con el id de la llanta.
        $repetidos = DB::table('llantas')
            ->selectRaw('lower(codigo) as clave')
            ->whereNotNull('codigo')
            ->groupByRaw('lower(codigo)')
            ->havingRaw('count(*) > 1')
            ->pluck('clave');

        foreach ($repetidos as $clave) {
            DB::table('llantas')->whereRaw('lower(codigo) = ?', [$clave])->orderBy('id')->skip(1)->take(PHP_INT_MAX)->get(['id', 'codigo'])
                ->each(fn (object $llanta) => DB::table('llantas')->where('id', $llanta->id)->update(['codigo' => "{$llanta->codigo}-{$llanta->id}"]));
        }

        DB::statement('create unique index llantas_codigo_unico on llantas (lower(codigo)) where codigo is not null');

        $llantas = DB::table('categorias_articulo')->where('clave', 'llantas')->value('id');

        if ($llantas === null) {
            return;
        }

        $ahora = now();

        if (DB::table('articulos')->where('categoria_articulo_id', $llantas)->whereRaw("lower(nombre) not like 'llanta%'")->exists()) {
            $accesorios = DB::table('categorias_articulo')->whereRaw('lower(nombre) = ?', ['accesorios de llantas'])->value('id')
                ?? DB::table('categorias_articulo')->insertGetId(['nombre' => 'Accesorios de llantas', 'created_at' => $ahora, 'updated_at' => $ahora]);

            DB::table('articulos')->where('categoria_articulo_id', $llantas)->whereRaw("lower(nombre) not like 'llanta%'")
                ->update(['categoria_articulo_id' => $accesorios, 'updated_at' => $ahora]);
        }

        DB::table('articulos')->whereNull('categoria_articulo_id')->whereRaw("lower(nombre) like 'llanta %'")
            ->update(['categoria_articulo_id' => $llantas, 'updated_at' => $ahora]);
    }

    public function down(): void
    {
        DB::statement('drop index if exists llantas_codigo_unico');

        Schema::table('llantas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detalle_ingreso_id');
            $table->dropColumn('costo');
        });
    }
};
