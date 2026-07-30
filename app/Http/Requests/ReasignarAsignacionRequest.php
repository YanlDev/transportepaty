<?php

namespace App\Http\Requests;

use App\Enums\TipoVehiculo;
use App\Models\Asignacion;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mover al conductor de una asignación vigente a otro tracto (y, si hace
 * falta, otra carreta), sin tocar quién es el conductor. Reutiliza las reglas
 * de {@see AsignacionRequest}: el fierro que la asignación ya tiene sigue
 * siendo una opción válida, igual que al editar, así que reasignar al mismo
 * tracto no rompe nada, solo no mueve nada.
 */
class ReasignarAsignacionRequest extends AsignacionRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracto_id' => [
                'required',
                $this->reglaVehiculoDelTipo(TipoVehiculo::Tracto),
                $this->reglaSinOtraAsignacionVigente('tracto_id'),
            ],
            'carreta_id' => [
                'nullable',
                $this->reglaVehiculoDelTipo(TipoVehiculo::Carreta),
                $this->reglaSinOtraAsignacionVigente('carreta_id'),
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function asignacionEnEdicion(): ?Asignacion
    {
        $asignacion = $this->route('asignacion');

        return $asignacion instanceof Asignacion ? $asignacion : null;
    }
}
