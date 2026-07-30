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
        Schema::table('estados_unidad', function (Blueprint $table) {
            // El día en que se espera que la unidad quede libre para un nuevo
            // viaje. Es un dato del reporte, no el día que se está mirando: una
            // unidad en ruta hoy puede ya saber que queda disponible pasado
            // mañana, y eso hay que poder anotarlo sin esperar a que llegue.
            $table->date('fecha_disponible')->nullable()->after('proximas_paradas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estados_unidad', function (Blueprint $table) {
            $table->dropColumn('fecha_disponible');
        });
    }
};
