<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Limpieza del catálogo pedida el 24-11-2026: las llantas dicen si son
     * flecha o mixta y su marca, lo mal escrito se corrige, el plástico se compra por
     * rollo y se entrega por metros, el limpia contacto repetido se da de
     * baja y los códigos sueltos pasan al formato del almacén (A-000NN).
     *
     * Cada cambio busca el artículo por su código y su nombre de antes: si
     * ya no está así, no toca nada.
     */
    public function up(): void
    {
        // Según la factura de Yudpol (FQQ1-004525) las cuatro Maxzes son de
        // flecha: el artículo vacío que decía lo mismo sobra.
        $sobra = DB::table('articulos')->where('codigo', 'A-00113')->first();

        if ($sobra !== null && (float) $sobra->stock === 0.0 && ! $this->estaEnUso((int) $sobra->id)) {
            DB::table('articulos')->where('id', $sobra->id)->delete();
        }

        // La marca es Onyx, como dice el pedido de J.CH. (20PD0007422).
        $nombres = [
            ['A-00114', 'LLANTA 11R 22.5 18PR - FLECHA MAXZES', 'MAXZES'],
            ['A-00115', 'LLANTA 11R 22.5 18PR - FLECHA ONYX', 'ONYX'],
            ['A-00116', 'LLANTA 11R 22.5 18PR - MIXTA ONYX', 'ONYX'],
            ['A-00020', 'GASOLINA', null],
            ['A-00021', 'SILICONA GREAT (PLOMA)', null],
        ];

        foreach ($nombres as [$codigo, $nombre, $marca]) {
            $articulo = DB::table('articulos')->where('codigo', $codigo)->first();

            // Solo si sigue siendo el mismo artículo: una llanta, la gasolina o la silicona.
            if ($articulo === null || ! preg_match('/^(LLANTA 11R|1 GALON DE GASOLINA|GASOLINA|S[OI]LICONA)/i', $articulo->nombre)) {
                continue;
            }

            DB::table('articulos')->where('id', $articulo->id)->update(array_filter(['nombre' => $nombre, 'marca' => $marca]));
        }

        // El limpia contacto está dos veces: queda el que tiene movimientos.
        $repetido = DB::table('articulos')->where('codigo', 'A-00024')->where('nombre', 'LIMPIA CONTACTO')->first();

        if ($repetido !== null && (float) $repetido->stock === 0.0 && ! $this->tieneMovimientos((int) $repetido->id)) {
            DB::table('articulos')->where('id', $repetido->id)->update(['activo' => false]);
        }

        // El plástico se compra por rollo (unos 40 m) y se entrega por metros.
        $plastico = DB::table('articulos')->where('codigo', 'A-00013')->where('unidad', 'rll')->first();

        if ($plastico !== null && ! $this->tieneMovimientos((int) $plastico->id)) {
            DB::table('articulos')->where('id', $plastico->id)->update([
                'unidad' => 'm',
                'unidad_compra' => 'rll',
                'factor_compra' => 40,
                // El costo que tenía era de una recepción anulada, y por rollo.
                'costo_promedio' => 0,
            ]);
        }

        // Los códigos sueltos pasan al formato del almacén, con su número.
        $sueltos = DB::table('articulos')->whereIn('codigo', ['1001', '1002', '1003', '1004', '1005', '1006', 'M0001'])->get(['id', 'codigo']);

        foreach ($sueltos as $articulo) {
            $codigo = sprintf('A-%05d', $articulo->id);

            if (! DB::table('articulos')->where('codigo', $codigo)->exists()) {
                DB::table('articulos')->where('id', $articulo->id)->update(['codigo' => $codigo]);
            }
        }
    }

    public function down(): void
    {
        // Es una corrección de datos: no se deshace.
    }

    /**
     * Si algo del sistema nombra al artículo: no se borra.
     */
    private function estaEnUso(int $articuloId): bool
    {
        foreach (['movimientos_almacen', 'detalles_ingreso', 'detalles_salida', 'detalles_inventario', 'detalles_vale_compra', 'repuestos_orden', 'repuestos_servicio', 'items_equipamiento', 'llantas'] as $tabla) {
            if (DB::table($tabla)->where('articulo_id', $articuloId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function tieneMovimientos(int $articuloId): bool
    {
        return DB::table('movimientos_almacen')->where('articulo_id', $articuloId)->exists();
    }
};
