<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un ticket puede quedar marcado «por revisar», con el motivo: los que
     * se armaron al regularizar y tienen algo que una persona debe mirar
     * (una GR sin trayecto, una bajada sin GR). Quien lo revisa quita la marca.
     */
    public function up(): void
    {
        Schema::table('vueltas', function (Blueprint $table) {
            $table->text('por_revisar')->nullable()->after('llegada_por');
        });
    }

    public function down(): void
    {
        Schema::table('vueltas', function (Blueprint $table) {
            $table->dropColumn('por_revisar');
        });
    }
};
