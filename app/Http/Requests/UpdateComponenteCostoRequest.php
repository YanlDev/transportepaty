<?php

namespace App\Http\Requests;

use App\Enums\NaturalezaCosto;
use App\Models\ComponenteCosto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El tipo y el método no se editan: cambiar «por día» por «por kilómetro», o
 * la forma de derivar la tasa, es otro componente. Lo que se ajusta acá es el
 * nombre, si el costo se atribuye al viaje o a la estructura, si sigue
 * vigente, y las entradas de su cuenta.
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
            'naturaleza' => ['required', Rule::enum(NaturalezaCosto::class)],
            'activo' => ['required', 'boolean'],
            'entradas' => ['required', 'array'],
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
        if (! $componente instanceof ComponenteCosto) {
            return [];
        }

        $reglas = [];

        foreach ($componente->metodo->calculador()->reglas() as $campo => $regla) {
            $reglas["entradas.{$campo}"] = $regla;
        }

        return $reglas;
    }
}
