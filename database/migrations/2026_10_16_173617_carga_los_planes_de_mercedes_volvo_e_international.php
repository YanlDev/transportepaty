<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los planes de las otras tres marcas, llevados a los mismos niveles
     * X · S · M · L que Scania. Salen de «4.14 Registro de mantenimiento
     * flota». Donde el fabricante pide un ítem a un km que no cae en ningún
     * nivel, se pasa al nivel anterior, nunca al siguiente: mejor cambiarlo
     * antes que pasarse. Esos ítems llevan en la descripción el intervalo del
     * fabricante, para que el taller lo ajuste desde la app si quiere.
     *
     * - **Mercedes Benz** (Actros 2648): el ciclo M de 60 mil es la X, M+Z1
     *   (120 mil) es la S y lo que va cada 240 mil es la M.
     * - **Volvo**: ítems a 25, 50, 75, 100, 125 y 150 mil, que quedan en X a
     *   25 mil, S a 75 mil y M a 150 mil.
     * - **International**: el plan ProStar LT (Cummins ISX15), 40 · 80 · 160
     *   mil, y una L a 320 mil para el refrigerante y el filtro del tanque de
     *   DEF.
     */
    public function up(): void
    {
        $planes = [
            'MERCEDES BENZ' => ['Preventivo Mercedes Benz (X · S · M)', [
                ['X', 1, 60000, null, [
                    ['Mano de obra de servicio', null, null, null],
                    ['Filtro de aceite', null, 1, 'und'],
                    ['Prefiltro de combustible', null, 1, 'und'],
                    ['Filtro separador de agua', null, 1, 'und'],
                    ['Filtro de aire', null, 1, 'und'],
                    ['Filtro de dirección', null, 1, 'und'],
                    ['Elemento filtrante', null, 1, 'und'],
                    ['Tapón de cárter de motor', null, 1, 'und'],
                    ['Aceite de motor Mobil Delvac Modern 10W-40', null, 39, 'L'],
                    ['Aceite de dirección Mobil ATF 220', null, 4.5, 'L'],
                    ['Grasa para chasis Mobil Grease MP', null, 1, 'kg'],
                    ['Materiales e insumos varios', null, null, null],
                ]],
                ['S', 2, 120000, null, [
                    ['Calibración de válvulas', null, 1, 'serv'],
                    ['Filtro de ventilación del tanque de combustible', null, 2, 'und'],
                    ['Filtro secador de aire', null, 1, 'und'],
                    ['Filtro de cabina', null, 1, 'und'],
                    ['Filtro de AdBlue', null, 1, 'und'],
                ]],
                ['M', 3, 240000, null, [
                    ['Junta anular de caja de cambios', null, 2, 'und'],
                    ['Aceite de caja MB 235.16 75W-85', null, 13, 'L'],
                    ['Aceite de caja MB 235.16 75W-90', null, 26, 'L'],
                    ['Refrigerante Radiacool SI-OAT', null, 30, 'L'],
                ]],
            ]],
            'VOLVO' => ['Preventivo Volvo (X · S · M)', [
                ['X', 1, 25000, 12, [
                    ['Aceite de motor VDS-4.5', null, 37, 'L'],
                    ['Filtro de aceite de motor (long life)', null, 2, 'und'],
                    ['Filtro de aceite de motor (by-pass)', null, 1, 'und'],
                    ['Filtro de combustible', null, 1, 'und'],
                    ['Filtro separador de agua de combustible', null, 1, 'und'],
                    ['Grasa de chasis y cabina', null, 2.5, 'kg'],
                    ['Filtro de aire primario (fabricante: 50 mil)', null, 1, 'und'],
                ]],
                ['S', 2, 75000, 12, [
                    ['Aceite de puente posterior', null, 34, 'L'],
                    ['Aceite de caja de cambios', null, 20, 'L'],
                    ['Filtro de aceite de caja de cambios', null, 1, 'und'],
                    ['Filtro de aire de admisión, primario y secundario (fabricante: 100 mil)', null, 1, 'und'],
                    ['Kit filtro del depósito de AdBlue (fabricante: 125 mil)', null, 1, 'und'],
                    ['Filtro de ventilación de AdBlue (fabricante: 125 mil)', null, 1, 'und'],
                    ['Filtro de AdBlue (fabricante: 125 mil)', null, 1, 'und'],
                    ['Kit filtro de unidad de bomba (fabricante: 125 mil)', null, 1, 'und'],
                    ['Colador de unidad de bomba (fabricante: 125 mil)', null, 1, 'und'],
                    ['Filtro de aire del tanque de combustible (fabricante: 125 mil)', null, 2, 'und'],
                    ['Filtro secador de aire (fabricante: 125 mil)', null, 1, 'und'],
                    ['Filtro de aire de cabina, A/C (fabricante: 125 mil)', null, 1, 'und'],
                    ['Filtro de aire de cabina, accesorios (fabricante: 125 mil)', null, 1, 'und'],
                ]],
                ['M', 3, 150000, 12, [
                    ['Aceite de dirección hidráulica', null, 7, 'L'],
                    ['Filtro de dirección', null, 1, 'und'],
                    ['Refrigerante anticorrosivo (fabricante: 48 meses)', null, 42, 'L'],
                ]],
            ]],
            'INTERNATIONAL' => ['Preventivo International (X · S · M · L)', [
                ['X', 1, 40000, 6, [
                    ['Aceite de motor SAE 15W-40 API CK-4', 'ILC704365G', 13, 'gal'],
                    ['Filtro de aceite de motor', 'LF14000NN', 1, 'und'],
                    ['Filtro de combustible', 'FF5776', 1, 'und'],
                    ['Filtro separador de combustible', 'FS19764', 1, 'und'],
                    ['Arandela de cárter', '4331267', 1, 'und'],
                    ['Filtro de aire primario', '2515573C1', 1, 'und'],
                    ['Filtro separador de aire DEF', 'AS2474', 1, 'und'],
                    ['Grasa NLGI EP2 Moly', 'ILC701708KG', 2, 'kg'],
                ]],
                ['S', 2, 80000, 12, [
                    ['Filtro de aire secundario', '1515575C2', 1, 'und'],
                    ['Aceite ATF Dexron III', 'TLC16542-1/4G', 4, 'und'],
                    ['Filtro de aceite de dirección', 'HF35476', 1, 'und'],
                    ['Aceite SAE 85W-140 API GL-5 mineral', 'ILC16528G', 0.3, 'gal'],
                    ['Filtro de aceite de diferencial', 'LF3341', 1, 'und'],
                    ['Filtro de aire de A/C', '4070948C1', 1, 'und'],
                    ['Filtro de refrigerante', 'WF2126', 1, 'und'],
                    ['Grasa dieléctrica', 'PHL80250', 6, 'und'],
                ]],
                ['M', 3, 160000, 12, [
                    ['Calibración de válvulas e inyectores', 'CX15803-625', 1, 'serv'],
                    ['Empaque de reservorio de dirección', '2602171C1', 1, 'und'],
                    ['Faja de ventilador', '4120384C1', 1, 'und'],
                    ['Faja de A/C', '3621194C2', 1, 'und'],
                    ['Fluido de embrague (12 oz)', 'FLT8F120ZKK', 2, 'und'],
                    ['Filtro secador de aire AD-IS', '8X5008414', 1, 'und'],
                    ['Aceite sintético TMTF (fabricante: 240 mil)', 'FLTMTF386PKK', 4, 'gal'],
                    ['Aceite sintético FE 75W-90 (fabricante: 240 mil)', 'FLTFE75W90P', 10, 'gal'],
                ]],
                ['L', 4, 320000, null, [
                    ['Refrigerante larga vida 50/50', 'FLTRELC5050G', 12, 'gal'],
                    ['Filtro del tanque de DEF', '2518088C1', 1, 'und'],
                ]],
            ]],
        ];

        $ahora = now();

        foreach ($planes as $marca => [$nombre, $servicios]) {
            if (DB::table('planes_mantenimiento')->where('marca', $marca)->exists()) {
                continue;
            }

            $planId = DB::table('planes_mantenimiento')->insertGetId([
                'marca' => $marca,
                'nombre' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            foreach ($servicios as [$codigo, $nivel, $intervaloKm, $intervaloMeses, $repuestos]) {
                $servicioId = DB::table('servicios_mantenimiento')->insertGetId([
                    'plan_mantenimiento_id' => $planId,
                    'codigo' => $codigo,
                    'nombre' => 'Servicio '.$codigo,
                    'nivel' => $nivel,
                    'intervalo_km' => $intervaloKm,
                    'intervalo_meses' => $intervaloMeses,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                foreach ($repuestos as $orden => [$descripcion, $codigoRepuesto, $cantidad, $unidad]) {
                    DB::table('repuestos_servicio')->insert([
                        'servicio_mantenimiento_id' => $servicioId,
                        'descripcion' => $descripcion,
                        'codigo' => $codigoRepuesto,
                        'cantidad' => $cantidad,
                        'unidad' => $unidad,
                        'orden' => $orden,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('planes_mantenimiento')
            ->whereIn('marca', ['MERCEDES BENZ', 'VOLVO', 'INTERNATIONAL'])
            ->delete();
    }
};
