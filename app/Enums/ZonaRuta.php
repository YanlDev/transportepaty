<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;
use App\Services\PalabrasDeZona;

/**
 * Las zonas en las que se mueve la flota, tan gruesas como hace falta para
 * leer el circuito: dónde carga y descarga la unidad, no la dirección exacta.
 *
 * El lado (sierra o costa) es lo que parte el ticket en dos: la ida sale de
 * Juliaca y termina en la costa, y la vuelta (el regreso) termina de nuevo
 * en Juliaca. Las regiones del sur (Arequipa, Moquegua,
 * Tacna) y Cusco cuentan como sierra porque se llega a ellas subiendo y desde
 * ahí la unidad sigue a la base.
 */
enum ZonaRuta: string
{
    use HasLabel;

    case Mina = 'mina';
    case Juliaca = 'juliaca';
    case Cusco = 'cusco';
    case Arequipa = 'arequipa';
    case Moquegua = 'moquegua';
    case Tacna = 'tacna';
    case Pisco = 'pisco';
    case Lima = 'lima';

    public function label(): string
    {
        return match ($this) {
            self::Mina => 'Mina San Rafael',
            self::Juliaca => 'Juliaca',
            self::Cusco => 'Cusco',
            self::Arequipa => 'Arequipa',
            self::Moquegua => 'Moquegua',
            self::Tacna => 'Tacna',
            self::Pisco => 'Pisco',
            self::Lima => 'Lima',
        };
    }

    public function esSierra(): bool
    {
        return in_array($this, [self::Mina, self::Juliaca, self::Cusco], true) || $this->esSur();
    }

    /**
     * Las regiones del sur, adonde va la carga particular: de ahí la unidad
     * sigue a Juliaca. Para armar el viaje cuentan las tres igual.
     */
    public function esSur(): bool
    {
        return in_array($this, [self::Arequipa, self::Moquegua, self::Tacna], true);
    }

    /**
     * Cuántos días suele tardar la unidad de una zona a otra, cargada o
     * vacía: de la sierra a la costa (Mina → Pisco) unos 3; del sur
     * (Arequipa, Moquegua, Tacna) a la costa o a Cusco 2; dentro de la sierra o de la costa (Juliaca → Mina,
     * Pisco → Callao) 1. Es lo normal sin contratiempos: sirve para dibujar
     * el viaje y saber cuándo llegó, no para cobrar.
     */
    public static function diasDeViaje(?self $origen, ?self $destino): int
    {
        if ($origen === null || $destino === null) {
            return 2;
        }

        $costa = fn (self $zona): bool => in_array($zona, [self::Pisco, self::Lima], true);

        if ($costa($origen) === $costa($destino)) {
            return in_array(self::Cusco, [$origen, $destino], true) && ($origen->esSur() || $destino->esSur()) ? 2 : 1;
        }

        return $origen->esSur() || $destino->esSur() ? 2 : 3;
    }

    /**
     * Donde empieza y termina la vuelta: solo Juliaca. Ahí se reinicia el
     * combustible: todo carro pasa por Juliaca antes de subir a la mina, y
     * lo que trae de la costa (escoria, materiales) le alcanza hasta ahí.
     * La mina, el sur y Cusco son de paso: de ahí la unidad sigue a Juliaca.
     */
    public function esBase(): bool
    {
        return $this === self::Juliaca;
    }

    /**
     * Las palabras de siempre, por si la tabla `palabras_zona` todavía no
     * existe o no se puede leer (los tests unitarios corren sin base).
     *
     * @var array<string, list<string>>
     */
    private const PALABRAS = [
        'mina' => ['ANTAUTA', 'SAN RAFAEL'],
        'pisco' => ['PARACAS', 'PISCO', 'CHINCHA'],
        'lima' => ['CALLAO', 'LIMA', 'HUARAL', 'CHILCA', 'LURIN', 'VENTANILLA', 'HUAURA', 'VEGUETA', 'HUAROCHIRI', 'CAÑETE', 'VILLA EL SALVADOR'],
        'juliaca' => ['JULIACA', 'SAN ROMAN', 'PUNO', 'CHUCUITO', 'EL COLLAO', 'ILAVE', 'DESAGUADERO'],
        'cusco' => ['CUSCO', 'QUISPICANCHI'],
        'arequipa' => ['AREQUIPA', 'CAYLLOMA', 'CASTILLA', 'ISLAY', 'CAMANA'],
        'moquegua' => ['MOQUEGUA', 'MARISCAL NIETO', 'ILO'],
        'tacna' => ['TACNA'],
    ];

    /**
     * El orden en que se prueban las zonas. La mina va primero porque su
     * dirección dice «Juliaca-Macusani» y se leería como Juliaca; Pisco
     * antes que Lima porque algunas direcciones de Paracas citan la
     * Panamericana «Lima».
     */
    private const ORDEN = ['mina', 'pisco', 'lima', 'juliaca', 'cusco', 'arequipa', 'moquegua', 'tacna'];

    /**
     * La zona de una dirección tal como viene en la GR («… - PARACAS - PISCO
     * - ICA»). Null si no se reconoce: quien llama decide qué hacer, esto no
     * adivina.
     */
    public static function desdeDireccion(string $direccion): ?self
    {
        $texto = mb_strtoupper($direccion);
        $palabras = app(PalabrasDeZona::class)->todas(self::PALABRAS);

        foreach (self::ORDEN as $zona) {
            foreach ($palabras[$zona] ?? [] as $palabra) {
                // Palabra entera: «ILO» no debe saltar con «KILOMETRO».
                if (preg_match('/\b'.preg_quote(mb_strtoupper($palabra), '/').'\b/u', $texto) === 1) {
                    return self::from($zona);
                }
            }
        }

        return null;
    }
}
