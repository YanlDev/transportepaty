<?php

use App\Models\Cliente;
use App\Models\Viaje;
use Illuminate\Database\Migrations\Migration;

/**
 * El padrón arranca con lo que ya se facturó: cada RUC distinto que aparece
 * en los viajes se da de alta como cliente, con la razón social más usada de
 * ese RUC y un alias corto sugerido. Así nadie tiene que tipear 26 clientes
 * a mano, y los viajes existentes quedan enlazados.
 *
 * Marca como recurrente a quien volvió: tiene viajes en al menos dos meses
 * distintos. Contar viajes sueltos no sirve —dos GR del mismo día no son un
 * cliente que regresa— y se puede corregir a mano desde la ficha.
 */
return new class extends Migration
{
    private const MESES_PARA_SER_RECURRENTE = 2;

    public function up(): void
    {
        // Sin los filtros globales que el modelo ganó después (las GR
        // anuladas): esta migración corre antes de que exista esa columna.
        $porRuc = Viaje::query()->withoutGlobalScopes()
            ->whereNotNull('cliente_ruc')
            ->get(['cliente', 'cliente_ruc', 'fecha_traslado'])
            ->groupBy('cliente_ruc');

        foreach ($porRuc as $ruc => $viajes) {
            // La razón social más repetida de ese RUC: si una GR la escribió
            // distinto, manda la grafía mayoritaria.
            // `countBy` deja la razón social como clave y PHP convierte a
            // entero las que parecen número; se devuelve a texto al leerla.
            $razonSocial = (string) $viajes
                ->countBy('cliente')
                ->sortDesc()
                ->keys()
                ->first();

            $cliente = Cliente::query()->firstOrCreate(
                ['ruc' => $ruc],
                [
                    'razon_social' => $razonSocial,
                    'alias' => Cliente::aliasSugerido($razonSocial),
                    'recurrente' => $viajes
                        ->map(fn (Viaje $viaje): string => $viaje->fecha_traslado->format('Y-m'))
                        ->unique()
                        ->count() >= self::MESES_PARA_SER_RECURRENTE,
                ],
            );

            Viaje::query()->withoutGlobalScopes()
                ->where('cliente_ruc', $ruc)
                ->update(['cliente_id' => $cliente->id]);
        }
    }

    /**
     * Solo desenlaza: los clientes creados se borran con la tabla si se
     * revierte la migración que la crea.
     */
    public function down(): void
    {
        Viaje::query()->withoutGlobalScopes()->update(['cliente_id' => null]);
    }
};
