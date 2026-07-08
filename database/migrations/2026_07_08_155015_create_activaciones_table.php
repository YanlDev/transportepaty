<?php

use App\Enums\ResultadoActivacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registro de Activación de Unidad en Reposo (Subproceso 3 / Anexo 1 del
     * Manual de Gestión de Flota): deja constancia de que una unidad en reposo
     * fue encendida y conducida brevemente para prevenir su deterioro.
     */
    public function up(): void
    {
        Schema::create('activaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            // Conductor / responsable de sucursal que ejecutó la activación.
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            // Usuario que registró la activación en el sistema.
            $table->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('fecha');
            $table->unsignedInteger('kilometraje')->nullable();
            $table->string('resultado')->default(ResultadoActivacion::SinNovedad->value);
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index(['vehiculo_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activaciones');
    }
};
