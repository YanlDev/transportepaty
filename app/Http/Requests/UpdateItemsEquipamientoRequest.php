<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El catálogo entero de una vez: los ítems con su mínimo, en orden. Un ítem
 * que ya existe llega con su `id`; uno nuevo, sin él. Los que no llegan se
 * dejan de contar, pero sus conteos anteriores se conservan.
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
     * Los ítems validados, en el orden en que llegaron.
     *
     * @return list<array{id: int|null, nombre: string, minimo: int|null}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $item): array => [
            'id' => isset($item['id']) ? (int) $item['id'] : null,
            'nombre' => (string) $item['nombre'],
            'minimo' => isset($item['minimo']) ? (int) $item['minimo'] : null,
        ], $this->validated('items')));
    }
}
