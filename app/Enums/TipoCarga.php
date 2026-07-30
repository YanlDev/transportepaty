<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Qué lleva la unidad. En este circuito la carga no es un dato suelto: define
 * en qué tramo va la unidad, para quién trabaja y por qué ruta debería estar
 * andando. De ahí que las reglas del negocio vivan acá y no repartidas por los
 * controladores.
 *
 * Las rutas se expresan con los códigos del catálogo de ubicaciones para que el
 * enum no dependa de la base de datos y pueda probarse suelto.
 */
enum TipoCarga: string
{
    use HasLabel;

    case Concentrado = 'concentrado';
    case Metalico = 'metalico';
    case Escoria = 'escoria';
    case Materiales = 'materiales';
    case Particular = 'particular';
    case Sacos = 'sacos';
    case Vacio = 'vacio';

    public function label(): string
    {
        return match ($this) {
            self::Concentrado => 'Concentrado',
            self::Metalico => 'Metálico',
            self::Escoria => 'Escoria',
            self::Materiales => 'Materiales',
            self::Particular => 'Particular',
            self::Sacos => 'Sacos',
            self::Vacio => 'Vacío',
        };
    }

    /**
     * Si la unidad va cargada o vacía. Se deduce del tipo de carga en vez de
     * pedirse aparte: es el mismo dato dicho de dos maneras, y tenerlo duplicado
     * en el Excel es justamente lo que produce filas que se contradicen solas.
     */
    public function estadoCarga(): EstadoCarga
    {
        return $this === self::Vacio
            ? EstadoCarga::Vacio
            : EstadoCarga::Cargado;
    }

    /**
     * Para quién es el viaje. Todo lo que se mueve dentro del circuito de mina
     * es de Minsur; la carga de terceros que se levanta en Lima es particular.
     *
     * Una unidad vacía todavía no tiene cliente, así que devuelve null en vez
     * de inventarle uno.
     */
    public function cliente(): ?Cliente
    {
        return match ($this) {
            self::Concentrado, self::Metalico, self::Escoria, self::Materiales, self::Sacos => Cliente::Minsur,
            self::Particular => Cliente::Particular,
            self::Vacio => null,
        };
    }

    /**
     * Tramo del circuito al que corresponde esta carga. Es lo que permite
     * detectar saltos imposibles entre un reporte y el siguiente: si la carga
     * de hoy pertenece a una fase que no se alcanza desde la de ayer, alguien
     * escribió mal una de las dos.
     */
    public function fase(): FaseCiclo
    {
        return match ($this) {
            self::Vacio => FaseCiclo::SubidaMina,
            self::Concentrado => FaseCiclo::MinaPisco,
            self::Metalico, self::Escoria, self::Materiales, self::Sacos => FaseCiclo::RetornoPisco,
            self::Particular => FaseCiclo::LimaJuliaca,
        };
    }

    /**
     * Rutas por las que esta carga puede circular, como pares de códigos de
     * ubicación. Cualquier otra combinación es un error de registro: los dos
     * más frecuentes son anotar la carga futura en vez de «vacío» cuando la
     * unidad sube a mina, y arrastrar la ruta del viaje anterior junto con la
     * carga nueva.
     *
     * @return array<int, array{origen: string, destino: string}>
     */
    public function rutasValidas(): array
    {
        return match ($this) {
            self::Concentrado => [
                ['origen' => 'san_rafael', 'destino' => 'pisco'],
            ],
            // El metálico va al puerto tanto si se carga en Pisco como si la
            // unidad se libera directo desde San Rafael.
            self::Metalico => [
                ['origen' => 'pisco', 'destino' => 'callao'],
                ['origen' => 'san_rafael', 'destino' => 'callao'],
            ],
            // La escoria y los sacos vuelven igual: cargados desde Pisco a mina.
            self::Escoria, self::Sacos => [
                ['origen' => 'pisco', 'destino' => 'san_rafael'],
            ],
            self::Materiales => [
                ['origen' => 'lima', 'destino' => 'san_rafael'],
                ['origen' => 'pisco', 'destino' => 'san_rafael'],
            ],
            // La carga particular varía de viaje en viaje —a veces con varias
            // paradas antes de volver a Juliaca— así que no tiene una ruta fija
            // que validar; anotar dónde va queda en `proximas_paradas`.
            self::Particular => [],
            self::Vacio => [
                ['origen' => 'juliaca', 'destino' => 'san_rafael'],
            ],
        };
    }

    /**
     * Puntos donde la carga de una unidad puede legítimamente cambiar: son los
     * extremos de las rutas declaradas, o sea donde alguien carga o descarga.
     * Se derivan de `rutasValidas()` en vez de marcarse en el catálogo, para que
     * no puedan quedar desfasados de las reglas.
     *
     * Sirve para detectar que una unidad cambió de carga en mitad del corredor,
     * donde no hay nada que cargar ni dónde descargar.
     *
     * @return list<string>
     */
    public static function puntosDeCambio(): array
    {
        $puntos = [];

        foreach (self::cases() as $carga) {
            foreach ($carga->rutasValidas() as $ruta) {
                $puntos[] = $ruta['origen'];
                $puntos[] = $ruta['destino'];
            }
        }

        return array_values(array_unique($puntos));
    }

    /**
     * Indica si el tipo de carga tiene rutas declaradas. Hoy las tienen todos,
     * pero se conserva como válvula: si mañana aparece una carga nueva antes de
     * saber por dónde circula, sus filas se importan sin validar la ruta en vez
     * de salir marcadas como error en cada reporte.
     */
    public function tieneRutasDefinidas(): bool
    {
        return $this->rutasValidas() !== [];
    }

    /**
     * Indica si esta carga puede ir del origen al destino indicados. Los tipos
     * sin rutas declaradas aceptan cualquiera, para no bloquear la carga de
     * datos por una regla que todavía no conocemos.
     */
    public function permiteRuta(string $origen, string $destino): bool
    {
        if (! $this->tieneRutasDefinidas()) {
            return true;
        }

        foreach ($this->rutasValidas() as $ruta) {
            if ($ruta['origen'] === $origen && $ruta['destino'] === $destino) {
                return true;
            }
        }

        return false;
    }
}
