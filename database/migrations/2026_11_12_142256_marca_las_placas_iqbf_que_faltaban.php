<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa la marca IQBF de las unidades con la consulta SUNAT del 12-11-2026.
 *
 * La consulta del 17-08-2026 que usó la migración anterior se cortaba en
 * `BWF991`: las 33 placas que SUNAT lista después (los Actros VEP/VEQ y varias
 * carretas) estaban inscritas pero quedaron sin marca. Los conductores de
 * ambas consultas son los mismos, así que no se tocan.
 */
return new class extends Migration
{
    /**
     * Placas de la sección 5.2 posteriores a `BWF991`.
     *
     * @var list<string>
     */
    private const PLACAS = [
        'BRI984', 'BRM970', 'BUV997', 'BVA997', 'BVB988', 'BVC980', 'BVS984', 'BWC987',
        'BVD974', 'BVT975', 'VEP771', 'VEP778', 'VEP779', 'VEP791', 'VEP793', 'VEP796',
        'VEP833', 'VEP853', 'VEP855', 'VEP856', 'VEP896', 'VEP897', 'VEP918', 'VEP924',
        'VEQ825', 'VFK989', 'VFO989', 'VGB972', 'VGB977', 'VGB993', 'VGT999', 'VGU980',
        'VJS982',
    ];

    public function up(): void
    {
        $ids = DB::table('vehiculos')
            ->get(['id', 'placa'])
            ->filter(fn (object $vehiculo): bool => in_array(
                strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $vehiculo->placa)),
                self::PLACAS,
                true,
            ))
            ->pluck('id');

        DB::table('vehiculos')->whereIn('id', $ids)->update(['iqbf' => true]);
    }

    public function down(): void
    {
        //
    }
};
