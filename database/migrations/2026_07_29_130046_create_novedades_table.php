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
        Schema::create('novedades', function (Blueprint $table) {
            $table->id();

            // Se registran contra el tracto, que es lo que identifica a la
            // unidad ante mina.
            $table->foreignId('tracto_id')->constrained('vehiculos')->cascadeOnDelete();

            $table->string('tipo');

            // `hasta` nulo significa que la novedad sigue vigente. Cerrarla con
            // fecha en vez de borrarla es lo que deja el rastro de por qué una
            // unidad no subió tal día.
            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->text('motivo')->nullable();

            $table->timestamps();

            $table->index(['tracto_id', 'desde']);
            $table->index('hasta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('novedades');
    }
};
