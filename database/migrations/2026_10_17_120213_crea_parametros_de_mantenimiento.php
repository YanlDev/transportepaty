<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los valores del módulo de mantenimiento que el taller ajusta desde la
     * app, en una sola fila como `parametros_flota`. Empieza con los cortes
     * de llantas: hasta cuántos mm de dibujo una llanta queda «para revisar»
     * y hasta cuántos «para cambiar». Se siembran 5 y 3 mm, los valores de
     * referencia con que arrancó el módulo.
     */
    public function up(): void
    {
        Schema::create('parametros_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->decimal('llantas_revisar_mm', 4, 1);
            $table->decimal('llantas_cambiar_mm', 4, 1);
            $table->timestamps();
        });

        DB::table('parametros_mantenimiento')->insert([
            'llantas_revisar_mm' => 5,
            'llantas_cambiar_mm' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_mantenimiento');
    }
};
