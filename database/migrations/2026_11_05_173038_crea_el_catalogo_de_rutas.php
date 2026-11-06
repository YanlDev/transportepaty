<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un solo catálogo para programar y abastecer: cada ruta es un tramo
     * estándar con su recorrido y su dotación (combustible por punto,
     * viáticos y peajes), con variantes cuando la dotación depende del peso.
     * Reemplaza a las opciones de tramo de la programación y a las tarjetas
     * de la pizarra, que describían lo mismo por separado.
     *
     * En el ticket queda una copia de cada ruta elegida (`rutas_vuelta`): lo
     * autorizado no cambia si después se edita el catálogo.
     */
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion')->nullable();
            // Ida, vuelta o null: un complemento que sirve en cualquiera.
            $table->string('sentido', 10)->nullable();
            // Se suma a la ruta principal del tramo (un tramo extra, una
            // carga de paso) en vez de definir el tramo.
            $table->boolean('es_complemento')->default(false);
            $table->json('paradas');
            $table->boolean('pide_destino')->default(false);
            $table->unsignedTinyInteger('dias_viaje')->nullable();
            $table->unsignedInteger('km_estandar')->nullable();
            $table->string('color', 20)->nullable();
            $table->text('nota')->nullable();
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('variantes_ruta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_id')->constrained('rutas')->cascadeOnDelete();
            $table->string('nombre', 60);
            // Toneladas: más de `peso_desde`, hasta `peso_hasta` inclusive.
            $table->decimal('peso_desde', 8, 2)->nullable();
            $table->decimal('peso_hasta', 8, 2)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('lineas_ruta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_id')->constrained('rutas')->cascadeOnDelete();
            // Null: vale para toda la ruta; si no, solo para esa variante.
            $table->foreignId('variante_ruta_id')->nullable()->constrained('variantes_ruta')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('concepto', 100);
            $table->foreignId('punto_abastecimiento_id')->nullable()->constrained('puntos_abastecimiento')->nullOnDelete();
            $table->decimal('galones', 7, 2)->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('rutas_vuelta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vuelta_id')->constrained('vueltas')->cascadeOnDelete();
            $table->string('sentido', 10);
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
            $table->foreignId('variante_ruta_id')->nullable()->constrained('variantes_ruta')->nullOnDelete();
            $table->boolean('es_complemento')->default(false);
            $table->string('nombre', 100);
            $table->string('variante', 60)->nullable();
            $table->string('color', 20)->nullable();
            // Las líneas como estaban al elegirla: tipo, concepto, punto,
            // galones y monto.
            $table->json('lineas');
            // La ruta tiene variantes y todavía no se eligió cuál: hasta
            // entonces el tope del tramo no se conoce.
            $table->boolean('variante_pendiente')->default(false);
            $table->unsignedTinyInteger('dias_viaje')->nullable();
            $table->unsignedInteger('km_estandar')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vuelta_id', 'sentido', 'orden']);
        });

        Schema::table('viaticos', function (Blueprint $table) {
            $table->foreignId('linea_ruta_id')->nullable()->after('conductor_id')->constrained('lineas_ruta')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linea_ruta_id');
        });

        Schema::dropIfExists('rutas_vuelta');
        Schema::dropIfExists('lineas_ruta');
        Schema::dropIfExists('variantes_ruta');
        Schema::dropIfExists('rutas');
    }
};
