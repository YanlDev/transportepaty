<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El plan preventivo Scania, tal como está en «4.14 Registro de
     * mantenimiento flota» (hoja SCANIA PLAN ANUAL): cuatro paquetes anidados
     * —X a los 20 mil km, S a los 40 mil, M a los 80 mil y L a los 160 mil—,
     * cada uno con los repuestos que suma a los del anterior.
     *
     * Va en una migración y no en un seeder para que producción lo tenga al
     * desplegar. Después se edita desde la app.
     */
    public function up(): void
    {
        $ahora = now();

        $planId = DB::table('planes_mantenimiento')->insertGetId([
            'marca' => 'SCANIA',
            'nombre' => 'Preventivo Scania (X · S · M · L)',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $servicios = [
            ['X', 'Servicio X', 1, 20000, [
                ['Mantenimiento del purificador centrífugo', 1, 'und'],
                ['Filtro de combustible', 1, 'und'],
                ['Filtro racor', 1, 'und'],
                ['Grasa', 2, 'kg'],
            ]],
            ['S', 'Servicio S', 2, 40000, [
                ['Filtro de aire primario', 1, 'und'],
                ['Aceite de motor', 41, 'L'],
                ['Filtro de aceite de motor', 1, 'und'],
            ]],
            ['M', 'Servicio M', 3, 80000, [
                ['Filtro de aire secundario', 1, 'und'],
                ['Filtro de dirección', 1, 'und'],
                ['Aceite de dirección', 4, 'L'],
                ['Filtro de retarder', 1, 'und'],
                ['Aceite de retarder', 8, 'L'],
                ['Filtro secador de aire', 1, 'und'],
                ['Líquido de freno', 2, 'L'],
            ]],
            ['L', 'Servicio L', 4, 160000, [
                ['Aceite de diferencial (1.er y 2.º eje)', 33, 'L'],
                ['Filtro de diferencial', 2, 'und'],
                ['Aceite de caja de cambios', 19, 'L'],
                ['Filtro de caja de cambios', 1, 'und'],
                ['Filtro de cabina', 1, 'und'],
                ['Calibración de válvulas', 1, 'serv'],
            ]],
        ];

        foreach ($servicios as [$codigo, $nombre, $nivel, $intervalo, $repuestos]) {
            $servicioId = DB::table('servicios_mantenimiento')->insertGetId([
                'plan_mantenimiento_id' => $planId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'nivel' => $nivel,
                'intervalo_km' => $intervalo,
                'intervalo_meses' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            foreach ($repuestos as $orden => [$descripcion, $cantidad, $unidad]) {
                DB::table('repuestos_servicio')->insert([
                    'servicio_mantenimiento_id' => $servicioId,
                    'descripcion' => $descripcion,
                    'cantidad' => $cantidad,
                    'unidad' => $unidad,
                    'orden' => $orden,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('planes_mantenimiento')->where('marca', 'SCANIA')->delete();
    }
};
