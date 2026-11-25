<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un complemento puede valer menos cuando el ticket ya lleva otra ruta
     * que cubre parte del camino: Huaral suma 20 gal desde Pisco, pero solo
     * 10 si la unidad ya llegó al Callao con el metálico (que ya dio 10).
     */
    public function up(): void
    {
        Schema::table('lineas_ruta', function (Blueprint $table) {
            $table->foreignId('si_lleva_ruta_id')->nullable()->after('galones')->constrained('rutas')->nullOnDelete();
            $table->decimal('galones_si_la_lleva', 7, 2)->nullable()->after('si_lleva_ruta_id');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_ruta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('si_lleva_ruta_id');
            $table->dropColumn('galones_si_la_lleva');
        });
    }
};
