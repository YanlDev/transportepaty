<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un aviso puede llevar un archivo ya guardado en el sistema: la
     * cotización del pedido que gerencia tiene que aprobar, por ejemplo.
     */
    public function up(): void
    {
        Schema::table('envios_whatsapp', function (Blueprint $table) {
            $table->unsignedBigInteger('adjunto_media_id')->nullable()->after('texto');
        });
    }

    public function down(): void
    {
        Schema::table('envios_whatsapp', function (Blueprint $table) {
            $table->dropColumn('adjunto_media_id');
        });
    }
};
