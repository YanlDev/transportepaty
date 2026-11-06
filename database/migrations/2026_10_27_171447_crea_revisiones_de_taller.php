<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El OK de taller de cada ticket: apto o no apto, con la fecha y hora en
     * que estará lista si no lo es, el motivo y los trabajos. Se guarda cada
     * revisión (no apto, después apto) y vale la última.
     */
    public function up(): void
    {
        Schema::create('revisiones_taller', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vuelta_id')->constrained('vueltas')->cascadeOnDelete();
            $table->string('resultado', 10);
            $table->dateTime('disponible_estimado')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->text('trabajos')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vuelta_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones_taller');
    }
};
