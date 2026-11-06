<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La GR ya no cae sola en un ticket: alguien la asigna, y queda quién y
     * cuándo. La que no va en ningún ticket (la unidad ya andaba en ruta
     * antes de empezar con los tickets) se marca «sin ticket» con su motivo,
     * para que no quede en la bandeja.
     */
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->foreignId('asignada_por')->nullable()->after('parada_vuelta_id')->constrained('users')->nullOnDelete();
            $table->timestamp('asignada_at')->nullable()->after('asignada_por');
            $table->timestamp('sin_ticket_at')->nullable()->after('asignada_at');
            $table->foreignId('sin_ticket_por')->nullable()->after('sin_ticket_at')->constrained('users')->nullOnDelete();
            $table->string('motivo_sin_ticket', 500)->nullable()->after('sin_ticket_por');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asignada_por');
            $table->dropConstrainedForeignId('sin_ticket_por');
            $table->dropColumn(['asignada_at', 'sin_ticket_at', 'motivo_sin_ticket']);
        });
    }
};
