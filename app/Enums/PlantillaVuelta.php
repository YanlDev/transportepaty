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
            self::JuliacaConcentradoMetalico => 'Particular a Juliaca · concentrado y metálico',
            self::JuliacaConcentrado => 'Particular a Juliaca · concentrado',
            self::MaterialesConcentradoMetalico => 'Materiales a mina · concentrado y metálico',
            self::MaterialesConcentrado => 'Materiales a mina · concentrado',
            self::EscoriaConcentrado => 'Escoria a mina · concentrado',
            self::SurConcentradoMetalico => 'Particular al Sur · concentrado y metálico',
            self::CuscoConcentradoMetalico => 'Particular a Cusco · concentrado y metálico',
            self::ParticularLimaJuliaca => 'Solo particular · Lima ↔ Juliaca',
        };
    }

    /**
     * Las paradas en orden: primero la ida, después el regreso.
     *
     * @return list<array{sentido: SentidoTramo, zona_origen: ZonaRuta, zona_destino: ZonaRuta, tipo_carga: TipoCarga}>
     */
    public function paradas(): array
    {
        $subidaParticular = fn (ZonaRuta $destino): array => [
            $this->parada(SentidoTramo::Ida, ZonaRuta::Lima, $destino, TipoCarga::Particular),
            $this->parada(SentidoTramo::Ida, $destino, ZonaRuta::Mina, TipoCarga::Vacio),
        ];
        $subidaMateriales = [$this->parada(SentidoTramo::Ida, ZonaRuta::Lima, ZonaRuta::Mina, TipoCarga::Materiales)];
        $bajadaConMetalico = [
            $this->parada(SentidoTramo::Regreso, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
            $this->parada(SentidoTramo::Regreso, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Metalico),
        ];
        $bajadaSinMetalico = [
            $this->parada(SentidoTramo::Regreso, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
            $this->parada(SentidoTramo::Regreso, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Vacio),
        ];

        return match ($this) {
            self::JuliacaConcentradoMetalico => [...$subidaParticular(ZonaRuta::Juliaca), ...$bajadaConMetalico],
            self::JuliacaConcentrado => [...$subidaParticular(ZonaRuta::Juliaca), ...$bajadaSinMetalico],
            self::MaterialesConcentradoMetalico => [...$subidaMateriales, ...$bajadaConMetalico],
            self::MaterialesConcentrado => [...$subidaMateriales, ...$bajadaSinMetalico],
            // Se queda en Pisco: la escoria sube y el concentrado vuelve a bajar ahí.
            self::EscoriaConcentrado => [
                $this->parada(SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Mina, TipoCarga::Escoria),
                $this->parada(SentidoTramo::Regreso, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
            ],
            self::SurConcentradoMetalico => [...$subidaParticular(ZonaRuta::Sur), ...$bajadaConMetalico],
            self::CuscoConcentradoMetalico => [...$subidaParticular(ZonaRuta::Cusco), ...$bajadaConMetalico],
            self::ParticularLimaJuliaca => [
                $this->parada(SentidoTramo::Ida, ZonaRuta::Lima, ZonaRuta::Juliaca, TipoCarga::Particular),
                $this->parada(SentidoTramo::Regreso, ZonaRuta::Juliaca, ZonaRuta::Lima, TipoCarga::Particular),
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
