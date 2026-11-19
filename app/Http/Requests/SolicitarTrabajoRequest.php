<?php

namespace App\Http\Requests;

use App\Enums\PrioridadOrden;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\EjecucionDeOrden;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Operaciones crea una pre-orden: qué unidad, qué le pasa, qué tan urgente
 * es y qué mecánico la va a ver. Puede llevar fotos de la falla.
 */
class SolicitarTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehiculo_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')->whereNull('deleted_at')],
            'trabajo' => ['required', 'string', 'min:5', 'max:5000'],
            'prioridad' => ['required', Rule::enum(PrioridadOrden::class)],
            // Quién arma el requerimiento: uno o varios mecánicos.
            'mecanicos' => ['required', 'array', 'min:1', 'max:10'],
            'mecanicos.*' => ['integer', 'distinct', Rule::in(app(EjecucionDeOrden::class)->mecanicosDisponibles()->modelKeys())],
            'responsable_id' => ['nullable', 'integer'],
            'fotos' => ['nullable', 'array', 'max:'.IniciarOrdenRequest::MAXIMO_FOTOS],
            'fotos.*' => ReglasDeArchivo::imagen(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehiculo_id.required' => 'Elige la unidad.',
            'mecanicos.required' => 'Elige al mecánico que va a armar el requerimiento.',
            'mecanicos.*.in' => 'Ese usuario no es mecánico o ya no está activo.',
            'trabajo.required' => 'Di qué le pasa a la unidad o qué hay que hacerle.',
            'trabajo.min' => 'Explica un poco más qué hay que hacer.',
            'fotos.*.max' => 'Cada foto debe pesar menos de 10 MB.',
            'fotos.*.mimetypes' => 'Cada foto debe ser JPG, PNG, WEBP o HEIC.',
        ];
    }
}
