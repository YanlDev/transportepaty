<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_mantenimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_mantenimiento');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('tipo_vehiculo')->nullable();
            $table->unsignedInteger('intervalo_km')->nullable();
            $table->unsignedSmallInteger('intervalo_meses')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('costo_estimado', 10, 2)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['marca', 'modelo']);
            $table->index('tipo_vehiculo');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_mantenimiento');
    }
};
