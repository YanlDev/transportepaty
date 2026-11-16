<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOTA_ANTERIOR = 'Va incluido en la dotación de «Concentrado a Pisco».';

    private const NOTA_NUEVA = 'Suma 10 galones a la ida: con metálico al Callao pasa de 220 a 230.';

    private const FRASE_ANTERIOR = 'el metálico al Callao o la vacía de Pisco a Lima no suman.';

    private const FRASE_NUEVA = 'la vacía de Pisco a Lima no suma; el metálico al Callao suma 10 galones.';

    /**
     * Cargar metálico de Pisco al Callao da 10 galones más: el complemento
     * «Metálico al Callao» deja de ir sin dotación y lleva su línea de
     * combustible. Si ya tiene combustible (lo editaron), no se toca.
     */
    public function up(): void
    {
        $rutaId = DB::table('rutas')->where('nombre', 'Metálico al Callao')->value('id');

        if ($rutaId === null) {
            return;
        }

        if (! DB::table('lineas_ruta')->where('ruta_id', $rutaId)->where('tipo', 'combustible')->exists()) {
            DB::table('lineas_ruta')->insert([
                'ruta_id' => $rutaId,
                'variante_ruta_id' => null,
                'tipo' => 'combustible',
                'concepto' => 'Metálico al Callao',
                'punto_abastecimiento_id' => null,
                'galones' => 10,
                'monto' => null,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->cambiarTexto(self::NOTA_ANTERIOR, self::NOTA_NUEVA, $rutaId);
        $this->cambiarTexto(self::FRASE_ANTERIOR, self::FRASE_NUEVA, DB::table('rutas')->where('nombre', 'Concentrado a Pisco')->value('id'));
    }

    public function down(): void
    {
        $rutaId = DB::table('rutas')->where('nombre', 'Metálico al Callao')->value('id');

        if ($rutaId === null) {
            return;
        }

        DB::table('lineas_ruta')->where('ruta_id', $rutaId)->where('tipo', 'combustible')
            ->where('concepto', 'Metálico al Callao')->delete();

        $this->cambiarTexto(self::NOTA_NUEVA, self::NOTA_ANTERIOR, $rutaId);
        $this->cambiarTexto(self::FRASE_NUEVA, self::FRASE_ANTERIOR, DB::table('rutas')->where('nombre', 'Concentrado a Pisco')->value('id'));
    }

    /**
     * Cambia una frase de la nota de una ruta, si todavía la tiene.
     */
    private function cambiarTexto(string $anterior, string $nuevo, ?int $rutaId): void
    {
        $nota = $rutaId === null ? null : DB::table('rutas')->where('id', $rutaId)->value('nota');

        if ($nota === null || ! str_contains($nota, $anterior)) {
            return;
        }

        DB::table('rutas')->where('id', $rutaId)->update([
            'nota' => str_replace($anterior, $nuevo, $nota),
            'updated_at' => now(),
        ]);
    }
};
