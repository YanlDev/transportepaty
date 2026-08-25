<?php

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
        Schema::create('descanso_debidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            // Siempre el primer día del mes: es la unidad en la que se
            // registra esta deuda, no un día puntual como en `asistencias`.
            $table->date('mes');
            // Con signo: positivo es la empresa debiéndole días al
            // conductor, negativo es al revés —descansó de más ese mes y
            // le debe un día de trabajo a la empresa.
            $table->tinyInteger('dias_debidos')->default(0);

            $table->timestamps();

            // Un conductor tiene a lo mucho un número de días debidos por mes.
            $table->unique(['conductor_id', 'mes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('descanso_debidos');
    }
};
