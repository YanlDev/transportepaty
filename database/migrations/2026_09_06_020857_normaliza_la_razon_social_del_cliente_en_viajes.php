<?php

use App\Services\ImportadorViaje;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La misma empresa venía escrita de dos formas en las GR reales («MINSUR
 * S.A.» y «MINSUR S. A.»), y al agrupar por nombre aparecía partida en dos
 * clientes distintos —dos barras en el tablero, dos opciones en el filtro—.
 * El importador ya empareja el nombre al guardar; esto arregla lo que entró
 * antes de esa regla.
 *
 * Solo toca `cliente`: en `destinatario` los sufijos distinguen puntos de
 * entrega reales («MP11 - San Rafael») y fusionarlos perdería el dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nombres = DB::table('viajes')->distinct()->pluck('cliente');

        foreach ($nombres as $nombre) {
            $normalizado = ImportadorViaje::normalizarRazonSocial($nombre);

            if ($normalizado === $nombre) {
                continue;
            }

            DB::table('viajes')
                ->where('cliente', $nombre)
                ->update(['cliente' => $normalizado]);
        }
    }

    /**
     * Irreversible a propósito: no se puede saber cuál de los viajes
     * emparejados venía con qué grafía, y volver a partirlos tampoco tendría
     * ningún valor.
     */
    public function down(): void {}
};
