<?php

use App\Models\IngresoAlmacen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Las compras se registran como dice el papel:
     *
     * - `fecha_emision`: la del comprobante, que puede ser anterior a la del
     *   ingreso al almacén (la que va al kardex).
     * - `descuento` y `flete`: lo que el comprobante resta o suma aparte de
     *   los ítems (fletes, pasajes, encomiendas). Se reparten en el costo.
     * - `comprobante_clave`: la serie y el número sin ceros de relleno, para
     *   reconocer la misma factura escrita de dos formas.
     * - `detalles_ingreso.bonificacion`: lo que el proveedor regala. Entra al
     *   almacén con un valor, pero no se paga.
     *
     * Ya no hay compras «sin comprobante»: las que hubiera pasan a «otro». Y
     * los comprobantes ya subidos se mueven al disco privado: en el público
     * se podían abrir sin iniciar sesión.
     */
    public function up(): void
    {
        Schema::table('ingresos_almacen', function (Blueprint $table) {
            $table->date('fecha_emision')->nullable()->after('fecha');
            $table->decimal('descuento', 14, 2)->default(0)->after('precios_con_igv');
            $table->decimal('flete', 14, 2)->default(0)->after('descuento');
            $table->string('comprobante_clave', 40)->nullable()->after('serie_numero');

            $table->index(['proveedor_id', 'comprobante_clave']);
        });

        Schema::table('detalles_ingreso', function (Blueprint $table) {
            $table->boolean('bonificacion')->default(false)->after('detalle_salida_id');
        });

        DB::table('ingresos_almacen')->where('tipo_comprobante', 'sin_comprobante')->update(['tipo_comprobante' => 'otro']);
        DB::table('ingresos_almacen')->where('tipo', 'compra')->update(['fecha_emision' => DB::raw('fecha')]);

        foreach (DB::table('ingresos_almacen')->whereNotNull('serie_numero')->get(['id', 'serie_numero']) as $ingreso) {
            DB::table('ingresos_almacen')->where('id', $ingreso->id)->update([
                'comprobante_clave' => IngresoAlmacen::claveComprobante($ingreso->serie_numero),
            ]);
        }

        $archivos = DB::table('media')
            ->where('model_type', (new IngresoAlmacen)->getMorphClass())
            ->where('collection_name', 'comprobante')
            ->where('disk', 'public')
            ->get(['id', 'file_name']);

        foreach ($archivos as $archivo) {
            $ruta = "{$archivo->id}/{$archivo->file_name}";

            if (Storage::disk('public')->exists($ruta)) {
                Storage::disk('local')->put($ruta, Storage::disk('public')->get($ruta));
                Storage::disk('public')->deleteDirectory((string) $archivo->id);
            }

            DB::table('media')->where('id', $archivo->id)->update(['disk' => 'local', 'conversions_disk' => 'local']);
        }
    }

    public function down(): void
    {
        Schema::table('detalles_ingreso', function (Blueprint $table) {
            $table->dropColumn('bonificacion');
        });

        Schema::table('ingresos_almacen', function (Blueprint $table) {
            $table->dropIndex(['proveedor_id', 'comprobante_clave']);
            $table->dropColumn(['fecha_emision', 'descuento', 'flete', 'comprobante_clave']);
        });
    }
};
