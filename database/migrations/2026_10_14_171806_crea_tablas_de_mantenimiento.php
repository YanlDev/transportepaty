<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El módulo de mantenimiento, con sus propias tablas: lee `vehiculos`
     * pero no escribe en ella.
     *
     * - `planes_mantenimiento`: uno por marca (Scania, Volvo…).
     * - `servicios_mantenimiento`: los paquetes del plan con su intervalo. El
     *   `nivel` dice cuáles contiene: la L de Scania (nivel 4) incluye la M,
     *   la S y la X, así que hacerla reinicia las cuatro.
     * - `repuestos_servicio`: lo que lleva cada paquete.
     * - `lecturas_kilometraje`: el km que se anota al llegar la unidad a base.
     * - `ordenes_trabajo`: el cuadro de trabajos de taller.
     */
    public function up(): void
    {
        Schema::create('planes_mantenimiento', function (Blueprint $table) {
            $table->id();
            // La marca tal como está en `vehiculos.marca`, en mayúsculas.
            $table->string('marca')->unique();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('servicios_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_mantenimiento_id')->constrained('planes_mantenimiento')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('nombre');
            $table->unsignedTinyInteger('nivel');
            $table->unsignedInteger('intervalo_km');
            $table->unsignedSmallInteger('intervalo_meses')->nullable();
            $table->timestamps();

            $table->unique(['plan_mantenimiento_id', 'codigo']);
        });

        Schema::create('repuestos_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_mantenimiento_id')->constrained('servicios_mantenimiento')->cascadeOnDelete();
            $table->string('descripcion');
            $table->string('codigo')->nullable();
            $table->decimal('cantidad', 8, 2)->nullable();
            $table->string('unidad', 20)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('lecturas_kilometraje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->unsignedInteger('kilometraje');
            $table->dateTime('leido_en');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->index(['vehiculo_id', 'leido_en']);
        });

        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->foreignId('servicio_mantenimiento_id')->nullable()->constrained('servicios_mantenimiento')->nullOnDelete();
            $table->string('estado', 20);
            $table->date('fecha');
            $table->unsignedInteger('kilometraje')->nullable();
            $table->dateTime('inicio')->nullable();
            $table->dateTime('termino')->nullable();
            $table->text('trabajo');
            $table->string('tecnico')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
        Schema::dropIfExists('lecturas_kilometraje');
        Schema::dropIfExists('repuestos_servicio');
        Schema::dropIfExists('servicios_mantenimiento');
        Schema::dropIfExists('planes_mantenimiento');
    }
};
