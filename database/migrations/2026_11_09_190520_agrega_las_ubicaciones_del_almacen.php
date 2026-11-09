<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La ubicación de un artículo pasa de texto libre a una lista de estantes
     * o zonas: así se elige en vez de escribirse («A-3», «a3», «Estante A3»
     * eran tres lugares distintos), el stock se filtra por estante y el
     * conteo se recorre estante por estante.
     *
     * Lo que ya se había escrito se convierte en ubicaciones, una por texto
     * distinto, y cada artículo queda apuntando a la suya.
     */
    public function up(): void
    {
        Schema::create('ubicaciones_almacen', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::table('articulos', function (Blueprint $table) {
            $table->foreignId('ubicacion_almacen_id')->nullable()->after('categoria_articulo_id')
                ->constrained('ubicaciones_almacen')->nullOnDelete();
        });

        $ahora = now();
        $textos = DB::table('articulos')
            ->whereNotNull('ubicacion')
            ->pluck('ubicacion')
            ->map(fn (string $texto): string => mb_strtoupper(trim($texto)))
            ->filter()
            ->unique();

        foreach ($textos as $nombre) {
            $id = DB::table('ubicaciones_almacen')->insertGetId([
                'nombre' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('articulos')
                ->whereRaw('upper(trim(ubicacion)) = ?', [$nombre])
                ->update(['ubicacion_almacen_id' => $id]);
        }

        Schema::table('articulos', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });
    }

    public function down(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            $table->string('ubicacion')->nullable();
        });

        foreach (DB::table('ubicaciones_almacen')->get() as $ubicacion) {
            DB::table('articulos')->where('ubicacion_almacen_id', $ubicacion->id)->update(['ubicacion' => $ubicacion->nombre]);
        }

        Schema::table('articulos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ubicacion_almacen_id');
        });

        Schema::dropIfExists('ubicaciones_almacen');
    }
};
