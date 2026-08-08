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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('estado');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Un conductor tiene a lo mucho un estado por día.
            $table->unique(['conductor_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
