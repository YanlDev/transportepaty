<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCombustible;
use App\Enums\TipoVehiculo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();

            $table->string('placa')->unique();
            $table->string('marca');
            $table->string('modelo');
            $table->unsignedSmallInteger('anio');
            $table->string('color')->nullable();
            $table->string('numero_serie')->nullable()->unique();
            $table->string('numero_motor')->nullable();

            $table->string('tipo')->default(TipoVehiculo::Camioneta->value);
            $table->string('combustible')->default(TipoCombustible::Diesel->value);
            $table->string('estado')->default(EstadoVehiculo::Activo->value);

            $table->unsignedInteger('kilometraje')->default(0);
            $table->date('fecha_adquisicion')->nullable();
            $table->date('soat_vence')->nullable();
            $table->date('revision_tecnica_vence')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sucursal_id', 'estado']);
            $table->index('conductor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
