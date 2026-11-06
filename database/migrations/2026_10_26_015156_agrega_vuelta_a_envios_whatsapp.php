<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los avisos por WhatsApp ahora salen de la vuelta (el ticket) y no de la
     * salida de carga particular: el envío recuerda a qué vuelta fue, con sus
     * ✓✓ como hasta ahora.
     */
    public function up(): void
    {
        Schema::table('envios_whatsapp', function (Blueprint $table) {
            $table->foreignId('vuelta_id')->nullable()->after('programacion_id')
                ->constrained('vueltas')->nullOnDelete();
            $table->index(['vuelta_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('envios_whatsapp', function (Blueprint $table) {
            $table->dropIndex(['vuelta_id', 'tipo']);
            $table->dropConstrainedForeignId('vuelta_id');
        });
    }
};
