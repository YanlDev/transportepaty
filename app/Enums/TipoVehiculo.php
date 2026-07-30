<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TipoVehiculo: string
{
    use HasLabel;

    case Tracto = 'tracto';
    case Carreta = 'carreta';

    public function label(): string
    {
        return match ($this) {
            self::Tracto => 'Tracto',
            self::Carreta => 'Carreta',
        };
    }

    /**
     * Indica si el tipo lleva caja de cambios. Solo el tracto es unidad
     * motriz; la carreta es remolcada y no tiene motor ni transmisión.
     */
    public function tieneCaja(): bool
    {
        return $this === self::Tracto;
    }

    /**
     * Tipos de documento que se pueden cargar para este tipo de vehículo,
     * incluido «Otro». Es lo que ofrece el formulario de carga.
     *
     * @return array<int, TipoDocumento>
     */
    public function documentosAplicables(): array
    {
        return array_values(array_filter(
            TipoDocumento::cases(),
            fn (TipoDocumento $documento): bool => $documento->aplicaA($this),
        ));
    }

    /**
     * Documentos sin los cuales la unidad no debería salir a ruta: TUC, tarjeta
     * de propiedad, revisión técnica de mercancías, MATPEL y —solo en el
     * tracto— el SOAT, que cubre a la unidad motriz. Es la lista contra la que
     * se calcula el semáforo documental.
     *
     * @return array<int, TipoDocumento>
     */
    public function documentosObligatorios(): array
    {
        return array_values(array_filter(
            $this->documentosAplicables(),
            fn (TipoDocumento $documento): bool => $documento->esObligatorio(),
        ));
    }
}
