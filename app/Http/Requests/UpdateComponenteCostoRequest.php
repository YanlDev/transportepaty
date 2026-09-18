<?php

namespace App\Http\Requests;

use App\Models\ComponenteCosto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El tipo y el método no se editan: cambiar «por día» por «por kilómetro» es
 * otra línea. Lo que se ajusta acá es el nombre, la tasa con la que se cotiza,
 * si sigue vigente, y las entradas de su calculadora de apoyo si la tiene.
 */
class UpdateComponenteCostoRequest extends FormRequest
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
        $componente = $this->route('componente');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tasa' => ['required', 'numeric', 'min:0'],
            'activo' => ['required', 'boolean'],
            'entradas' => ['sometimes', 'array'],
            // Cada método sabe qué campos necesita y cuáles no pueden ser cero
            // —quien divide entre el rendimiento es quien sabe que no puede
            // serlo—, así que las reglas de las entradas salen de ahí.
            ...$this->reglasDeLasEntradas($componente),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function reglasDeLasEntradas(?ComponenteCosto $componente): array
    {
        if (! $componente instanceof ComponenteCosto || ! $this->has('entradas')) {
            return [];
        }

        $reglas = [];

        foreach ($componente->metodo->calculador()->reglas() as $campo => $regla) {
            $reglas["entradas.{$campo}"] = $regla;
        }

        return $reglas;
    }
}
