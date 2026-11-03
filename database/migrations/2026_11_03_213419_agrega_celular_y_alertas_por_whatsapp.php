<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El celular de cada usuario, para mandarle sus alertas por WhatsApp, y
     * en los envíos lo que necesita una alerta: a qué usuario fue, qué tipo
     * de alerta era y el texto ya armado (una alerta no se rearma al mandarla
     * como el aviso de un ticket).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('celular', 20)->nullable()->after('email');
        });

        Schema::table('envios_whatsapp', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('area_aviso_id')->constrained('users')->nullOnDelete();
            $table->string('alerta', 40)->nullable()->after('tipo');
            $table->text('texto')->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('envios_whatsapp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['alerta', 'texto']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('celular');
        });
    }
};
