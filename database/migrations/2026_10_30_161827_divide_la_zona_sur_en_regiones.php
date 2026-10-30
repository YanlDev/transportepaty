<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La zona «Sur» se parte en sus regiones: Arequipa, Moquegua y Tacna.
     * Para armar el viaje cuentan igual que antes; la diferencia es que cada
     * región tiene sus palabras y sus puntos.
     *
     * Lo guardado con «sur» se reparte por el lugar que nombra (Ilo y
     * Mariscal Nieto son de Moquegua); lo que no nombra ninguno queda en
     * Arequipa, la más grande.
     */
    private const REGIONES = [
        'tacna' => ['TACNA', 'PUCAMARCA', 'TARATA', 'JORGE BASADRE', 'CANDARAVE'],
        'moquegua' => ['MOQUEGUA', 'MARISCAL NIETO', 'ILO', 'GENERAL SANCHEZ CERRO'],
        'arequipa' => ['AREQUIPA', 'CAYLLOMA', 'CASTILLA', 'ISLAY', 'CAMANA', 'CARAVELI', 'CONDESUYOS', 'LA UNION', 'MAJES'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('palabras_zona')->where('zona', 'sur')->get() as $palabra) {
                DB::table('palabras_zona')->where('id', $palabra->id)->update(['zona' => $this->region($palabra->palabra)]);
            }

            foreach (DB::table('destinos_particulares')->where('zona', 'sur')->get() as $destino) {
                DB::table('destinos_particulares')->where('id', $destino->id)->update(['zona' => $this->region($destino->lugar)]);
            }

            foreach (DB::table('puntos_abastecimiento')->where('zona', 'sur')->get() as $punto) {
                DB::table('puntos_abastecimiento')->where('id', $punto->id)->update(['zona' => $this->region($punto->nombre.' '.$punto->detalle)]);
            }

            $reglas = DB::table('reglas_abastecimiento')->where('zona_origen', 'sur')->orWhere('zona_destino', 'sur')->get();

            foreach ($reglas as $regla) {
                $region = $this->region($regla->nombre.' '.$regla->lugares);
                DB::table('reglas_abastecimiento')->where('id', $regla->id)->update([
                    'zona_origen' => $regla->zona_origen === 'sur' ? $region : $regla->zona_origen,
                    'zona_destino' => $regla->zona_destino === 'sur' ? $region : $regla->zona_destino,
                ]);
            }

            $paradas = DB::table('paradas_vuelta')->where('zona_origen', 'sur')->orWhere('zona_destino', 'sur')->get();

            foreach ($paradas as $parada) {
                DB::table('paradas_vuelta')->where('id', $parada->id)->update([
                    'zona_origen' => $parada->zona_origen === 'sur' ? $this->region((string) $parada->lugar_origen) : $parada->zona_origen,
                    'zona_destino' => $parada->zona_destino === 'sur' ? $this->region((string) $parada->lugar_destino) : $parada->zona_destino,
                ]);
            }

            foreach (DB::table('opciones_tramo')->where('paradas', 'like', '%"sur"%')->get() as $opcion) {
                DB::table('opciones_tramo')->where('id', $opcion->id)
                    ->update(['paradas' => str_replace('"sur"', '"arequipa"', $opcion->paradas)]);
            }
        });
    }

    /**
     * Volver atrás junta las regiones otra vez en «sur».
     */
    public function down(): void
    {
        $regiones = array_keys(self::REGIONES);

        DB::table('palabras_zona')->whereIn('zona', $regiones)->update(['zona' => 'sur']);
        DB::table('destinos_particulares')->whereIn('zona', $regiones)->update(['zona' => 'sur']);
        DB::table('puntos_abastecimiento')->whereIn('zona', $regiones)->update(['zona' => 'sur']);
        DB::table('reglas_abastecimiento')->whereIn('zona_origen', $regiones)->update(['zona_origen' => 'sur']);
        DB::table('reglas_abastecimiento')->whereIn('zona_destino', $regiones)->update(['zona_destino' => 'sur']);
        DB::table('paradas_vuelta')->whereIn('zona_origen', $regiones)->update(['zona_origen' => 'sur']);
        DB::table('paradas_vuelta')->whereIn('zona_destino', $regiones)->update(['zona_destino' => 'sur']);
    }

    /**
     * La región del texto: la primera cuya palabra aparece entera.
     */
    private function region(string $texto): string
    {
        $texto = mb_strtoupper($texto);

        foreach (self::REGIONES as $region => $palabras) {
            foreach ($palabras as $palabra) {
                if (preg_match('/\b'.preg_quote($palabra, '/').'\b/u', $texto) === 1) {
                    return $region;
                }
            }
        }

        return 'arequipa';
    }
};
