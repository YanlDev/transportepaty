<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lleva a la calculadora del combustible el precio del Diesel B5 S-50 en los
 * grifos al 18/09/2026: la mediana de lo registrado en los últimos 30 días en
 * «Últimos precios registrados EVPC» de Osinergmin, la misma base que muestra
 * Facilito.
 *
 * Solo cambia lo que sugiere la calculadora. La tasa con la que se cotiza la
 * decide quien arma el tarifario, con «Usar esta tasa».
 */
return new class extends Migration
{
    /**
     * @var array<string, array{antes: float, ahora: float}>
     */
    private const PRECIOS = [
        'Arequipa' => ['antes' => 22.30, 'ahora' => 25.49],
        'Nazca' => ['antes' => 22.05, 'ahora' => 25.29],
        'Lima' => ['antes' => 22.68, 'ahora' => 25.09],
    ];

    public function up(): void
    {
        $this->fijarPrecios('ahora');
    }

    public function down(): void
    {
        $this->fijarPrecios('antes');
    }

    /**
     * Toca solo el precio de las localidades conocidas: si alguien agregó otra
     * o cambió su participación, eso queda como estaba.
     *
     * @param  'antes'|'ahora'  $momento
     */
    private function fijarPrecios(string $momento): void
    {
        $componente = DB::table('componentes_costo')
            ->where('nombre', 'Consumo de combustible')
            ->first();

        if ($componente === null) {
            return;
        }

        $entradas = json_decode($componente->entradas, true) ?? [];

        $entradas['localidades'] = array_map(
            fn (array $localidad): array => isset(self::PRECIOS[$localidad['nombre'] ?? ''])
                ? [...$localidad, 'precio_galon' => self::PRECIOS[$localidad['nombre']][$momento]]
                : $localidad,
            $entradas['localidades'] ?? [],
        );

        DB::table('componentes_costo')
            ->where('id', $componente->id)
            ->update(['entradas' => json_encode($entradas), 'updated_at' => now()]);
    }
};
