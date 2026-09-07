<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;
use App\Services\Costos\Metodo;
use App\Services\Costos\MetodoActivo;
use App\Services\Costos\MetodoCicloVida;
use App\Services\Costos\MetodoCombustible;
use App\Services\Costos\MetodoFrecuenciaKm;
use App\Services\Costos\MetodoManual;
use App\Services\Costos\MetodoPlanillaConductor;
use App\Services\Costos\MetodoProrrateoAnual;

/**
 * De dónde sale la tasa de un componente. Cada método es la cadena de cuentas
 * que antes vivía en una hoja del Excel de costos: la planilla del conductor,
 * la mezcla de precios de diésel, el ciclo de vida de los neumáticos.
 *
 * Tenerlos acá y no como números sueltos es lo que permite responder «¿por qué
 * el día de conductor cuesta esto?» sin abrir otro archivo, y actualizar el
 * sueldo base o el precio del galón sin recalcular a mano.
 */
enum MetodoCosto: string
{
    use HasLabel;

    case Manual = 'manual';
    case ProrrateoAnual = 'prorrateo_anual';
    case PlanillaConductor = 'planilla_conductor';
    case Activo = 'activo';
    case Combustible = 'combustible';
    case CicloVida = 'ciclo_vida';
    case FrecuenciaKm = 'frecuencia_km';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Tasa fija',
            self::ProrrateoAnual => 'Prorrateo de un monto anual',
            self::PlanillaConductor => 'Planilla del conductor',
            self::Activo => 'Depreciación y capital del activo',
            self::Combustible => 'Precio del combustible y rendimiento',
            self::CicloVida => 'Ciclo de vida (vidas sucesivas)',
            self::FrecuenciaKm => 'Servicios por frecuencia de kilómetros',
        };
    }

    /**
     * Quién sabe hacer la cuenta de este método.
     */
    public function calculador(): Metodo
    {
        return match ($this) {
            self::Manual => new MetodoManual,
            self::ProrrateoAnual => new MetodoProrrateoAnual,
            self::PlanillaConductor => new MetodoPlanillaConductor,
            self::Activo => new MetodoActivo,
            self::Combustible => new MetodoCombustible,
            self::CicloVida => new MetodoCicloVida,
            self::FrecuenciaKm => new MetodoFrecuenciaKm,
        };
    }
}
