<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos cosas del aviso por WhatsApp:
 *
 * - Qué áreas reciben el recordatorio de las unidades que siguen sin GR a
 *   la hora que se configure en el panel.
 * - Cuándo cambió por última vez lo que dice el aviso de una salida
 *   (unidad, conductor, cliente, destino o fecha): si fue después de
 *   avisar, la tarjeta lo marca para que se reenvíe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas_aviso', function (Blueprint $table): void {
            $table->boolean('recibe_recordatorio')->default(false);
        });

        Schema::table('programaciones', function (Blueprint $table): void {
            $table->timestamp('datos_cambiados_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->dropColumn('datos_cambiados_at');
        });

        Schema::table('areas_aviso', function (Blueprint $table): void {
            $table->dropColumn('recibe_recordatorio');
        });
    }
};
