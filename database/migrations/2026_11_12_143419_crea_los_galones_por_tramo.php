<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los galones de cada tramo (Lima → Arequipa, Arequipa → Juliaca…), cargado
 * y vacío. Una ruta «por tramos» no lleva galones escritos a mano: suma los
 * de sus tramos, y en el ticket los de sus paradas reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galones_por_tramo', function (Blueprint $table) {
            $table->id();
            $table->string('zona_origen', 20);
            $table->string('zona_destino', 20);
            $table->decimal('galones_cargado', 7, 2)->nullable();
            $table->decimal('galones_vacio', 7, 2)->nullable();
            $table->string('nota', 200)->nullable();
            $table->timestamps();

            $table->unique(['zona_origen', 'zona_destino']);
        });

        Schema::table('rutas', function (Blueprint $table) {
            $table->boolean('por_tramos')->default(false)->after('es_complemento');
        });

        Schema::table('rutas_vuelta', function (Blueprint $table) {
            $table->boolean('por_tramos')->default(false)->after('es_complemento');
        });
    }

    public function down(): void
    {
        Schema::table('rutas_vuelta', fn (Blueprint $table) => $table->dropColumn('por_tramos'));
        Schema::table('rutas', fn (Blueprint $table) => $table->dropColumn('por_tramos'));
        Schema::dropIfExists('galones_por_tramo');
    }
};
