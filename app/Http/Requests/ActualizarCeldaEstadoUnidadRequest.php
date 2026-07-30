<?php

namespace App\Http\Requests;

use App\Enums\Cliente;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Models\EstadoUnidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarCeldaEstadoUnidadRequest extends FormRequest
{
    /**
     * El estado del día puede no existir todavía cuando se edita la primera
     * celda de la fila, así que no hay un modelo sobre el que apoyar la
     * policy: create y update de EstadoUnidadPolicy hacen la misma
     * comprobación (rol admin) hoy, así que basta con `create`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', EstadoUnidad::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'campo' => ['required', Rule::in([
                ...EstadoUnidad::CAMPOS_CONFIRMABLES,
                'observaciones',
                'proximas_paradas',
                'fecha_disponible',
            ])],
            'valor' => $this->reglaDelValor(),
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    private function reglaDelValor(): array
    {
        return match ($this->string('campo')->value()) {
            'carreta_id' => ['nullable', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Carreta->value)],
            'conductor_id' => ['nullable', 'exists:conductores,id'],
            'tipo_carga' => ['nullable', Rule::enum(TipoCarga::class)],
            'cliente' => ['nullable', Rule::enum(Cliente::class)],
            'origen_id', 'destino_id', 'ubicacion_id' => ['nullable', 'exists:ubicaciones,id'],
            'observaciones', 'proximas_paradas' => ['nullable', 'string', 'max:1000'],
            'fecha_disponible' => ['nullable', 'date'],
            default => ['nullable'],
        };
    }
}
