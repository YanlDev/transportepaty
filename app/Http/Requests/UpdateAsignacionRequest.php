<?php

namespace App\Http\Requests;

use App\Models\Asignacion;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateAsignacionRequest extends AsignacionRequest
{
    /**
     * La edición sí permite corregir la fecha de inicio, que al registrar se
     * puso sola. Solo se editan asignaciones vigentes, así que nunca hay un
     * `hasta` que deba quedar por delante.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'desde' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    protected function asignacionEnEdicion(): ?Asignacion
    {
        $asignacion = $this->route('asignacion');

        return $asignacion instanceof Asignacion ? $asignacion : null;
    }
}
