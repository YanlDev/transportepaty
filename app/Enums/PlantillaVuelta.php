<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Las vueltas que más se repiten, para programar con un clic. Salen del
 * análisis de las GR de julio a octubre de 2026 (ver
 * `docs/plan-expediente-de-viaje.md`): las siete de mina cubren el 71 % de
 * las vueltas, y la de particular es la de las unidades que no suben a mina.
 *
 * Son un punto de partida: al programar se puede quitar, agregar o cambiar
 * cualquier parada, y en el camino se edita el regreso si cambia el plan.
 */
enum PlantillaVuelta: string
{
    use HasLabel;

    case JuliacaConcentradoMetalico = 'juliaca_conc_met';
    case JuliacaConcentrado = 'juliaca_conc';
    case MaterialesConcentradoMetalico = 'materiales_conc_met';
    case MaterialesConcentrado = 'materiales_conc';
    case EscoriaConcentrado = 'escoria_conc';
    case SurConcentradoMetalico = 'sur_conc_met';
    case CuscoConcentradoMetalico = 'cusco_conc_met';
    case ParticularLimaJuliaca = 'particular_lima_juliaca';

    public function label(): string
    {
        return match ($this) {
            self::JuliacaConcentradoMetalico => 'Concentrado y metálico · particular a Juliaca',
            self::JuliacaConcentrado => 'Concentrado · particular a Juliaca',
            self::MaterialesConcentradoMetalico => 'Concentrado y metálico · materiales a mina',
            self::MaterialesConcentrado => 'Concentrado · materiales a mina',
            self::EscoriaConcentrado => 'Concentrado · escoria a mina',
            self::SurConcentradoMetalico => 'Concentrado y metálico · particular al Sur',
            self::CuscoConcentradoMetalico => 'Concentrado y metálico · particular a Cusco',
            self::ParticularLimaJuliaca => 'Solo particular · Juliaca ↔ Lima',
        };
    }

    /**
     * Las paradas en orden: primero la ida (baja desde la mina o desde
     * Juliaca), después el regreso (vuelve a subir).
     *
     * @return list<array{sentido: SentidoTramo, zona_origen: ZonaRuta, zona_destino: ZonaRuta, tipo_carga: TipoCarga}>
     */
    public function paradas(): array
    {
        $bajadaConMetalico = [
            $this->parada(SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
            $this->parada(SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Metalico),
        ];
        $bajadaSinMetalico = [
            $this->parada(SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
            $this->parada(SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Vacio),
        ];
        $subidaParticular = fn (ZonaRuta $destino): array => [
            $this->parada(SentidoTramo::Regreso, ZonaRuta::Lima, $destino, TipoCarga::Particular),
            $this->parada(SentidoTramo::Regreso, $destino, ZonaRuta::Mina, TipoCarga::Vacio),
        ];
        $subidaMateriales = [$this->parada(SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Mina, TipoCarga::Materiales)];

        return match ($this) {
            self::JuliacaConcentradoMetalico => [...$bajadaConMetalico, ...$subidaParticular(ZonaRuta::Juliaca)],
            self::JuliacaConcentrado => [...$bajadaSinMetalico, ...$subidaParticular(ZonaRuta::Juliaca)],
            self::MaterialesConcentradoMetalico => [...$bajadaConMetalico, ...$subidaMateriales],
            self::MaterialesConcentrado => [...$bajadaSinMetalico, ...$subidaMateriales],
            // Se da la vuelta en Pisco: baja el concentrado y sube la escoria.
            self::EscoriaConcentrado => [
                $this->parada(SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
                $this->parada(SentidoTramo::Regreso, ZonaRuta::Pisco, ZonaRuta::Mina, TipoCarga::Escoria),
            ],
            self::SurConcentradoMetalico => [...$bajadaConMetalico, ...$subidaParticular(ZonaRuta::Sur)],
            self::CuscoConcentradoMetalico => [...$bajadaConMetalico, ...$subidaParticular(ZonaRuta::Cusco)],
            self::ParticularLimaJuliaca => [
                $this->parada(SentidoTramo::Ida, ZonaRuta::Juliaca, ZonaRuta::Lima, TipoCarga::Particular),
                $this->parada(SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Juliaca, TipoCarga::Particular),
            ],
        };
    }

    /**
     * Las plantillas como opciones para el formulario, con sus paradas.
     *
     * @return list<array{value: string, label: string, paradas: list<array{sentido: string, zona_origen: string, zona_destino: string, tipo_carga: string}>}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $plantilla): array => [
            'value' => $plantilla->value,
            'label' => $plantilla->label(),
            'paradas' => array_map(fn (array $parada): array => [
                'sentido' => $parada['sentido']->value,
                'zona_origen' => $parada['zona_origen']->value,
                'zona_destino' => $parada['zona_destino']->value,
                'tipo_carga' => $parada['tipo_carga']->value,
            ], $plantilla->paradas()),
        ], self::cases());
    }

    /**
     * @return array{sentido: SentidoTramo, zona_origen: ZonaRuta, zona_destino: ZonaRuta, tipo_carga: TipoCarga}
     */
    private function parada(SentidoTramo $sentido, ZonaRuta $origen, ZonaRuta $destino, TipoCarga $carga): array
    {
        return ['sentido' => $sentido, 'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga];
    }
}
