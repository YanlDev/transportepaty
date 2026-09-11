<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restos del módulo de guías electrónicas que se retiró: emitir la GRE contra
 * SUNAT desde acá. `Viaje` nunca las declaró en su `#[Fillable]` ni en sus
 * casts, así que ni siquiera eran escribibles.
 *
 * Se comprobó antes de borrarlas que no había nada que perder: los dos puntos
 * y las tres columnas de GRE estaban en null en las 453 filas, y
 * `motivo_traslado` tenía el default '04' en todas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
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

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->foreignId('punto_partida_id')->nullable()->after('destino')
                ->constrained('puntos_traslado')->nullOnDelete();
            $table->foreignId('punto_llegada_id')->nullable()->after('punto_partida_id')
                ->constrained('puntos_traslado')->nullOnDelete();
            $table->string('motivo_traslado', 2)->default('04')->after('punto_llegada_id');
            $table->string('gre_estado')->default('pendiente')->after('observaciones');
            $table->string('gre_ticket')->nullable()->after('gre_estado');
            $table->json('gre_respuesta')->nullable()->after('gre_ticket');

            $table->index('gre_estado');
        });
    }
};
