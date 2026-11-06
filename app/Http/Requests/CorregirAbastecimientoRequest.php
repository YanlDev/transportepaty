<?php

namespace App\Http\Requests;

use App\Models\Abastecimiento;

/**
 * Corregir un combustible ya autorizado: las mismas reglas que al
 * registrarlo (ruta del tramo, tope, motivo si lo pasa), pero el ticket y la
 * unidad son los del registro y sus propios galones no cuentan contra el
 * tope.
 */
class CorregirAbastecimientoRequest extends RegistrarAbastecimientoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->abastecimiento()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehiculo_id' => $this->abastecimiento()->vehiculo_id,
            'vuelta_id' => $this->abastecimiento()->vuelta_id,
        ]);
    }

    protected function registroQueSeCorrige(): ?int
    {
        return $this->abastecimiento()->id;
    }

    /**
     * Con voucher, el registro cuenta lo que de verdad cargó: corregir lo
     * autorizado no cambia lo que suma al tramo.
     */
    protected function galonesQueSeSuman(): float
    {
        return $this->abastecimiento()->tieneDespacho()
            ? $this->abastecimiento()->galonesQueCuentan()
            : parent::galonesQueSeSuman();
    }

    private function abastecimiento(): Abastecimiento
    {
        /** @var Abastecimiento $abastecimiento */
        $abastecimiento = $this->route('abastecimiento');

        return $abastecimiento;
    }
}
