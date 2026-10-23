<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si la detracción se escribió a mano. A veces el 4% calculado no
     * coincide con lo que el cliente depositó; entonces se corrige la cifra y
     * el neto sale de ella. Las facturas existentes siguen calculadas.
     *
     * En una factura subida en PDF se guarda aparte la detracción impresa,
     * para poder volver a ella después de corregirla a mano. Las ya subidas
     * la toman de la que tienen, que hasta hoy era siempre la impresa.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('detraccion_manual')->default(false)->after('detraccion_redondeada');
            $table->decimal('detraccion_impresa', 12, 2)->nullable()->after('detraccion_manual');
        });

        DB::table('facturas')
            ->where('desde_pdf', true)
            ->update(['detraccion_impresa' => DB::raw('detraccion')]);
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['detraccion_manual', 'detraccion_impresa']);
        });
    }
};
