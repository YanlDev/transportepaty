<?php

use App\Enums\TipoCarga;
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
        Schema::table('viajes', function (Blueprint $table) {
            // No hay forma de leerlo del PDF de la GR (ese dato vive en cómo el
            // usuario clasifica los archivos en su carpeta local), así que
            // arranca en Particular y se corrige a mano desde la tabla.
            $table->string('tipo_carga')->default(TipoCarga::Particular->value)->after('destino');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropColumn('tipo_carga');
        });
    }
};
