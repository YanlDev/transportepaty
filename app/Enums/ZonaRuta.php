<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Las zonas en las que se mueve la flota, tan gruesas como hace falta para
 * leer el circuito: dónde carga y descarga la unidad, no la dirección exacta.
 *
 * El lado (sierra o costa) es lo que parte la vuelta en dos: la ida sale de
 * Juliaca o de la mina y termina en la costa (la bajada), y el regreso
 * termina de vuelta en la sierra (la subida). El Sur y Cusco cuentan como
 * sierra porque se llega a ellos subiendo y desde ahí la unidad sigue a mina.
 */
enum ZonaRuta: string
{
    use HasLabel;

    case Mina = 'mina';
    case Juliaca = 'juliaca';
    case Cusco = 'cusco';
    case Sur = 'sur';
    case Pisco = 'pisco';
    case Lima = 'lima';

    public function label(): string
    {
        return match ($this) {
            self::Mina => 'Mina San Rafael',
            self::Juliaca => 'Juliaca',
            self::Cusco => 'Cusco',
            self::Sur => 'Sur',
            self::Pisco => 'Pisco',
            self::Lima => 'Lima',
        };
    }

    public function esSierra(): bool
    {
        return in_array($this, [self::Mina, self::Juliaca, self::Cusco, self::Sur], true);
    }

    /**
     * Donde empieza y termina la vuelta: Juliaca y la mina. El Sur y Cusco
     * son destinos de paso de la carga particular: de ahí la unidad sigue a
     * Juliaca.
     */
    public function esBase(): bool
    {
        return in_array($this, [self::Mina, self::Juliaca], true);
    }

    /**
     * Las palabras de la dirección de la GR que delatan cada zona, en el
     * orden en que se prueban. La mina va primero porque su dirección dice
     * «Juliaca-Macusani» y se leería como Juliaca; Pisco antes que Lima
     * porque algunas direcciones de Paracas citan la Panamericana «Lima».
     *
     * @var array<string, list<string>>
     */
    private const PALABRAS = [
        'mina' => ['ANTAUTA', 'SAN RAFAEL'],
        'pisco' => ['PARACAS', 'PISCO', 'CHINCHA'],
        // Villa El Salvador aparece en direcciones cortadas que no llegan a
        // decir «Lima».
        'lima' => ['CALLAO', 'LIMA', 'HUARAL', 'CHILCA', 'LURIN', 'VENTANILLA', 'HUAURA', 'VEGUETA', 'HUAROCHIRI', 'CAÑETE', 'VILLA EL SALVADOR'],
        'juliaca' => ['JULIACA', 'SAN ROMAN', 'PUNO', 'CHUCUITO', 'EL COLLAO', 'ILAVE', 'DESAGUADERO'],
        'cusco' => ['CUSCO', 'QUISPICANCHI'],
        'sur' => ['AREQUIPA', 'CAYLLOMA', 'CASTILLA', 'ISLAY', 'CAMANA', 'TACNA', 'MOQUEGUA', 'MARISCAL NIETO', 'ILO'],
    ];

    /**
     * La zona de una dirección tal como viene en la GR («… - PARACAS - PISCO
     * - ICA»). Null si no se reconoce: quien llama decide qué hacer, esto no
     * adivina.
     */
    public static function desdeDireccion(string $direccion): ?self
    {
        $texto = mb_strtoupper($direccion);

        foreach (self::PALABRAS as $zona => $palabras) {
            foreach ($palabras as $palabra) {
                // Palabra entera: «ILO» no debe saltar con «KILOMETRO».
                if (preg_match('/\b'.preg_quote($palabra, '/').'\b/u', $texto) === 1) {
                    return self::from($zona);
                }
            }
        }

        return null;
    }
}
