<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Las filas del kardex. Las entradas traen su costo; las salidas se llevan el
 * costo promedio del momento.
 */
enum TipoMovimientoAlmacen: string
{
    use HasLabel;

    case SaldoInicial = 'saldo_inicial';
    case Compra = 'compra';
    case Devolucion = 'devolucion';
    case AjusteEntrada = 'ajuste_entrada';
    case Salida = 'salida';
    case AjusteSalida = 'ajuste_salida';

    public function label(): string
    {
        return match ($this) {
            self::SaldoInicial => 'Saldo inicial',
            self::Compra => 'Compra',
            self::Devolucion => 'Devolución',
            self::AjusteEntrada => 'Sobrante de inventario',
            self::Salida => 'Salida',
            self::AjusteSalida => 'Faltante de inventario',
        };
    }

    public function esEntrada(): bool
    {
        return in_array($this, [self::SaldoInicial, self::Compra, self::Devolucion, self::AjusteEntrada], true);
    }

    /**
     * El código de la tabla 12 de SUNAT (tipo de operación) para el kardex
     * valorizado: 16 saldo inicial, 02 compra, 24 devolución recibida,
     * 10 salida a producción (el consumo del taller), 28 ajuste por
     * diferencia de inventario.
     */
    public function codigoSunat(): string
    {
        return match ($this) {
            self::SaldoInicial => '16',
            self::Compra => '02',
            self::Devolucion => '24',
            self::Salida => '10',
            self::AjusteEntrada, self::AjusteSalida => '28',
        };
    }
}
