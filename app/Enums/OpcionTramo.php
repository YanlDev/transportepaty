<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Lo que se programa: un tramo a la vez. Cuando la unidad baja todavía no
 * se sabe si va a volver con escoria, materiales o carga particular, así que
 * la ida se programa al salir y el regreso cuando se sabe.
 *
 * Las opciones salen de las vueltas reales (ver
 * `docs/plan-expediente-de-viaje.md`). La carga particular de regreso pide
 * un destino: si no es Juliaca, la unidad descarga ahí y sigue a Juliaca.
 */
enum OpcionTramo: string
{
    use HasLabel;

    case ConcentradoPisco = 'concentrado_pisco';
    case ConcentradoMetalico = 'concentrado_metalico';
    case ParticularLima = 'particular_lima';
    case VacioLima = 'vacio_lima';
    case EscoriaMina = 'escoria_mina';
    case MaterialesMina = 'materiales_mina';
    case Particular = 'particular';
    case VacioJuliaca = 'vacio_juliaca';

    public function label(): string
    {
        return match ($this) {
            self::ConcentradoPisco => 'Concentrado a Pisco',
            self::ConcentradoMetalico => 'Concentrado + metálico al Callao',
            self::ParticularLima => 'Particular a Lima',
            self::VacioLima => 'Vacía a Lima',
            self::EscoriaMina => 'Escoria a mina',
            self::MaterialesMina => 'Materiales a mina',
            self::Particular => 'Carga particular',
            self::VacioJuliaca => 'Vacía a Juliaca',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::ConcentradoPisco => 'Mina → Pisco',
            self::ConcentradoMetalico => 'Mina → Pisco → Callao',
            self::ParticularLima => 'Juliaca → Lima',
            self::VacioLima => 'Juliaca ⇢ Lima',
            self::EscoriaMina => 'Pisco → Mina',
            self::MaterialesMina => 'Lima → Mina (Ransa)',
            self::Particular => 'Lima → destino, y sigue a Juliaca',
            self::VacioJuliaca => 'Lima ⇢ Juliaca',
        };
    }

    public function sentido(): SentidoTramo
    {
        return match ($this) {
            self::ConcentradoPisco, self::ConcentradoMetalico, self::ParticularLima, self::VacioLima => SentidoTramo::Ida,
            default => SentidoTramo::Regreso,
        };
    }

    /**
     * Las paradas del tramo. La de carga particular de regreso tiene el
     * destino vacío: lo elige quien programa.
     *
     * @return list<array{zona_origen: ZonaRuta, zona_destino: ZonaRuta|null, tipo_carga: TipoCarga}>
     */
    public function paradas(): array
    {
        $parada = fn (ZonaRuta $origen, ?ZonaRuta $destino, TipoCarga $carga): array => [
            'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga,
        ];

        return match ($this) {
            self::ConcentradoPisco => [$parada(ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado)],
            self::ConcentradoMetalico => [
                $parada(ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado),
                $parada(ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Metalico),
            ],
            self::ParticularLima => [$parada(ZonaRuta::Juliaca, ZonaRuta::Lima, TipoCarga::Particular)],
            self::VacioLima => [$parada(ZonaRuta::Juliaca, ZonaRuta::Lima, TipoCarga::Vacio)],
            self::EscoriaMina => [$parada(ZonaRuta::Pisco, ZonaRuta::Mina, TipoCarga::Escoria)],
            self::MaterialesMina => [$parada(ZonaRuta::Lima, ZonaRuta::Mina, TipoCarga::Materiales)],
            self::Particular => [$parada(ZonaRuta::Lima, null, TipoCarga::Particular)],
            self::VacioJuliaca => [$parada(ZonaRuta::Lima, ZonaRuta::Juliaca, TipoCarga::Vacio)],
        };
    }

    public function pideDestino(): bool
    {
        return $this === self::Particular;
    }

    /**
     * A dónde va la carga particular de regreso. Todos menos Juliaca son de
     * paso: la unidad descarga y sigue vacía a Juliaca.
     *
     * @var list<array{lugar: string, zona: ZonaRuta}>
     */
    private const DESTINOS_PARTICULAR = [
        ['lugar' => 'Juliaca', 'zona' => ZonaRuta::Juliaca],
        ['lugar' => 'Arequipa', 'zona' => ZonaRuta::Sur],
        ['lugar' => 'Tacna', 'zona' => ZonaRuta::Sur],
        ['lugar' => 'Moquegua', 'zona' => ZonaRuta::Sur],
        ['lugar' => 'Ilo', 'zona' => ZonaRuta::Sur],
        ['lugar' => 'Cusco', 'zona' => ZonaRuta::Cusco],
        ['lugar' => 'Puno', 'zona' => ZonaRuta::Juliaca],
    ];

    /**
     * Las opciones para el formulario, separadas por tramo.
     *
     * @return array{ida: list<array<string, mixed>>, regreso: list<array<string, mixed>>, destinos: list<array{lugar: string, zona: string}>}
     */
    public static function opciones(): array
    {
        $porSentido = ['ida' => [], 'regreso' => []];

        foreach (self::cases() as $opcion) {
            $porSentido[$opcion->sentido()->value][] = [
                'value' => $opcion->value,
                'label' => $opcion->label(),
                'descripcion' => $opcion->descripcion(),
                'pide_destino' => $opcion->pideDestino(),
                'paradas' => array_map(fn (array $parada): array => [
                    'zona_origen' => $parada['zona_origen']->value,
                    'zona_destino' => $parada['zona_destino']?->value,
                    'tipo_carga' => $parada['tipo_carga']->value,
                ], $opcion->paradas()),
            ];
        }

        return [
            ...$porSentido,
            'destinos' => array_map(fn (array $destino): array => [
                'lugar' => $destino['lugar'],
                'zona' => $destino['zona']->value,
            ], self::DESTINOS_PARTICULAR),
        ];
    }
}
