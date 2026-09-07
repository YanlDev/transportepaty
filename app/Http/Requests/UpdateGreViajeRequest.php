<?php

namespace App\Http\Requests;

use App\Enums\MotivoTraslado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los datos del viaje que solo existen para la guía electrónica: de dónde a
 * dónde según el catálogo con ubigeo, y por qué se mueve la carga.
 */
class UpdateGreViajeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Nullable para poder dejar un viaje a medio completar: los puntos
            // se cargan cuando se conocen, no todos de una vez.
            'punto_partida_id' => ['nullable', 'integer', Rule::exists('puntos_traslado', 'id')],
            'punto_llegada_id' => ['nullable', 'integer', Rule::exists('puntos_traslado', 'id')],
            'motivo_traslado' => ['required', Rule::enum(MotivoTraslado::class)],
        ];
    }
}
