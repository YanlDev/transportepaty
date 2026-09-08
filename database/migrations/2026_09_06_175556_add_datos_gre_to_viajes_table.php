<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            // Los puntos quedan como catálogo y no como texto porque la GRE
            // exige ubigeo, y el ubigeo no se deduce de la dirección escrita a
            // mano. `origen`/`destino` se conservan: son lo que dice el PDF
            // importado y no siempre coinciden con el punto elegido.
            $table->foreignId('punto_partida_id')->nullable()->after('destino')->constrained('puntos_traslado')->nullOnDelete();
            $table->foreignId('punto_llegada_id')->nullable()->after('punto_partida_id')->constrained('puntos_traslado')->nullOnDelete();

            // Los valores van literales: los enums que los nombraban se fueron
            // con el módulo de guías, y la migración ya corrió en producción.
            $table->string('motivo_traslado', 2)->default('04')->after('punto_llegada_id');

            // Estado del documento ante SUNAT. Los viajes que ya existen vienen
            // de PDF emitidos desde el portal SOL, así que nacen en `pendiente`
            // y solo cambian si se emiten desde acá.
            $table->string('gre_estado')->default('pendiente')->after('observaciones');
            $table->string('gre_ticket')->nullable()->after('gre_estado');

            // Respuesta cruda del CDR: código, descripción y notas. Se guarda
            // completa porque cuando SUNAT rechaza, el detalle es el único
            // insumo para corregir.
            $table->json('gre_respuesta')->nullable()->after('gre_ticket');

            $table->index('gre_estado');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropForeign(['punto_partida_id']);
            $table->dropForeign(['punto_llegada_id']);
            $table->dropIndex(['gre_estado']);
            $table->dropColumn([
                'punto_partida_id',
                'punto_llegada_id',
                'motivo_traslado',
                'gre_estado',
                'gre_ticket',
                'gre_respuesta',
            ]);
        });
    }
};
