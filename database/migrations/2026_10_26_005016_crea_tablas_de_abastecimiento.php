<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El área de abastecimiento: la pizarra de dotaciones hecha datos y el
     * registro de lo que se le autoriza a cada unidad.
     *
     * - `puntos_abastecimiento`: dónde se carga (Kio, la mina, Arequipa,
     *   Nazca…).
     * - `reglas_abastecimiento`: cada tarjeta de la pizarra. A qué trayecto
     *   aplica (todo opcional: sentido, zonas, carga, un lugar, un rango de
     *   toneladas) y sus líneas.
     * - `lineas_regla_abastecimiento`: cada fila de la tarjeta: galones en un
     *   punto (o tanque lleno), viáticos o peajes.
     * - `abastecimientos`: lo que abastecimiento autorizó por el QR de
     *   Repsol, por unidad, punto y vuelta.
     */
    public function up(): void
    {
        Schema::create('puntos_abastecimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('zona', 20)->nullable();
            $table->string('detalle')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('reglas_abastecimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('color', 20)->nullable();
            $table->string('sentido', 10)->nullable();
            $table->string('zona_origen', 20)->nullable();
            $table->string('zona_destino', 20)->nullable();
            $table->string('tipo_carga', 20)->nullable();
            // Palabras que tienen que aparecer en el origen o el destino de
            // la GR («CHILCA», «MOQUEGUA, ILO»): separan rutas de la misma zona.
            $table->string('lugares')->nullable();
            $table->decimal('peso_desde', 8, 2)->nullable();
            $table->decimal('peso_hasta', 8, 2)->nullable();
            $table->text('nota')->nullable();
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('lineas_regla_abastecimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regla_abastecimiento_id')->constrained('reglas_abastecimiento')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('concepto', 100);
            $table->foreignId('punto_abastecimiento_id')->nullable()->constrained('puntos_abastecimiento')->nullOnDelete();
            $table->decimal('galones', 7, 2)->nullable();
            $table->boolean('tanque_lleno')->default(false);
            $table->decimal('monto', 10, 2)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('abastecimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignId('vuelta_id')->nullable()->constrained('vueltas')->nullOnDelete();
            $table->foreignId('punto_abastecimiento_id')->nullable()->constrained('puntos_abastecimiento')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('galones', 7, 2);
            $table->boolean('tanque_lleno')->default(false);
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha']);
            $table->index('vuelta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimientos');
        Schema::dropIfExists('lineas_regla_abastecimiento');
        Schema::dropIfExists('reglas_abastecimiento');
        Schema::dropIfExists('puntos_abastecimiento');
    }
};
