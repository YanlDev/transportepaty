<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantenimiento_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mantenimiento_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas_mantenimiento')->nullOnDelete();
            $table->string('nombre');
            $table->string('tipo_mantenimiento');
            $table->decimal('costo', 10, 2)->nullable();
            $table->timestamps();

            $table->index('mantenimiento_id');
            $table->index('plantilla_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimiento_items');
    }
};
