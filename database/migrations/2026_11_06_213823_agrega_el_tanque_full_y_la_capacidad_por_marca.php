<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El tanque full vuelve, sin ambigüedad: en Kio se llena el tanque y lo
     * que entró lo dice el voucher (con su foto y el kilometraje). No tiene
     * galones fijos, así que no suma al tope del tramo: va aparte.
     *
     * - Una línea de combustible de la ruta puede ser «tanque full».
     * - Un combustible autorizado puede ser «tanque full».
     * - La capacidad del tanque por marca sirve para avisar de un voucher
     *   que no cabe (un error de tipeo), sin bloquearlo.
     *
     * Las líneas de Kio que quedaron con galones por definir el 05-11 (eran
     * el tanque full de la pizarra) pasan a ser tanque full, también en la
     * copia de los tickets que las tienen.
     */
    public function up(): void
    {
        Schema::table('lineas_ruta', function (Blueprint $table) {
            $table->boolean('tanque_lleno')->default(false)->after('galones');
        });

        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->boolean('tanque_lleno')->default(false)->after('galones');
        });

        Schema::create('capacidades_tanque', function (Blueprint $table) {
            $table->id();
            $table->string('marca', 60)->unique();
            $table->decimal('galones', 6, 1);
            $table->timestamps();
        });

        $kio = DB::table('puntos_abastecimiento')->where('nombre', 'Kio')->value('id');

        if ($kio === null) {
            return;
        }

        $lineas = DB::table('lineas_ruta')
            ->where('tipo', 'combustible')
            ->where('punto_abastecimiento_id', $kio)
            ->whereNull('galones')
            ->pluck('id');

        DB::table('lineas_ruta')->whereIn('id', $lineas)->update(['tanque_lleno' => true, 'updated_at' => now()]);

        $ids = $lineas->all();

        DB::table('rutas_vuelta')->orderBy('id')->get(['id', 'lineas'])->each(function (object $copia) use ($ids): void {
            $lineasCopia = json_decode($copia->lineas, true) ?: [];
            $cambio = false;

            foreach ($lineasCopia as &$linea) {
                if (in_array($linea['linea_id'] ?? null, $ids, true)) {
                    $linea['tanque_lleno'] = true;
                    $cambio = true;
                }
            }

            if ($cambio) {
                DB::table('rutas_vuelta')->where('id', $copia->id)->update(['lineas' => json_encode($lineasCopia)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacidades_tanque');

        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->dropColumn('tanque_lleno');
        });

        Schema::table('lineas_ruta', function (Blueprint $table) {
            $table->dropColumn('tanque_lleno');
        });
    }
};
