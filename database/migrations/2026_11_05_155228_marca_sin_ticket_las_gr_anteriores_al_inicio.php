<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los tickets empiezan el 05-11-2026: las GR de antes que no están en un
     * ticket salen de la bandeja de «GR por asignar» con su motivo.
     */
    public function up(): void
    {
        DB::table('viajes')
            ->whereNull('parada_vuelta_id')
            ->whereNull('sin_ticket_at')
            ->whereDate('fecha_traslado', '<', '2026-11-05')
            ->update([
                'sin_ticket_at' => now(),
                'motivo_sin_ticket' => 'Anterior al inicio de los tickets (05-11-2026).',
            ]);
    }

    public function down(): void
    {
        DB::table('viajes')
            ->where('motivo_sin_ticket', 'Anterior al inicio de los tickets (05-11-2026).')
            ->update(['sin_ticket_at' => null, 'motivo_sin_ticket' => null]);
    }
};
