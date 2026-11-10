<?php

namespace App\Http\Requests;

use App\Enums\AplicaEquipamiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El catálogo del equipamiento entero, de una vez: cada ítem con su nombre,
 * a qué unidades aplica, si lleva vencimiento y el mínimo que deben llevar.
 */
class UpdateItemsEquipamientoRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['nullable', 'integer', 'distinct', 'exists:items_equipamiento,id'],
            'items.*.nombre' => ['required', 'string', 'max:255'],
            'items.*.minimo' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items.*.aplica_a' => ['nullable', Rule::enum(AplicaEquipamiento::class)],
            'items.*.lleva_vencimiento' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.nombre.required' => 'Cada ítem necesita su nombre.',
        ];
    }

    /**
     * @return list<array{id: int|null, nombre: string, minimo: int|null, aplica_a: string|null, lleva_vencimiento: bool|null}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $item): array => [
            'id' => isset($item['id']) ? (int) $item['id'] : null,
            'nombre' => (string) $item['nombre'],
            'minimo' => isset($item['minimo']) ? (int) $item['minimo'] : null,
            'aplica_a' => isset($item['aplica_a']) ? (string) $item['aplica_a'] : null,
            'lleva_vencimiento' => isset($item['lleva_vencimiento']) ? (bool) $item['lleva_vencimiento'] : null,
        ], $this->validated('items')));
    }
}
