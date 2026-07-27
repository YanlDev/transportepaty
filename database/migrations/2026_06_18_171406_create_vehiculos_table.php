<?php

use App\Enums\EstadoVehiculo;
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

            $table->string('placa')->unique();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->unsignedSmallInteger('anio')->nullable();

            $table->string('tipo')->default(TipoVehiculo::Tracto->value);
            $table->string('estado')->default(EstadoVehiculo::Activo->value);

            // Solo aplica a tractos: la carreta es remolcada y no tiene transmisión.
            $table->string('caja', 20)->nullable();

            $table->string('vin')->nullable();
            $table->string('numero_motor')->nullable();
            $table->string('color')->nullable();
            $table->unsignedSmallInteger('ejes')->nullable();

            // Pesos en kilogramos, según tarjeta de propiedad.
            $table->unsignedInteger('peso_neto')->nullable();
            $table->unsignedInteger('peso_bruto')->nullable();
            $table->unsignedInteger('carga_util')->nullable();

            $table->date('fecha_adquisicion')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
            $table->index('tipo');
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
