<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_realizado' => ['sometimes', 'required', 'date'],
            'odometro' => ['sometimes', 'required', 'integer', 'min:0'],
            'proveedor' => ['nullable', 'string', 'max:255'],
            'factura_numero' => ['nullable', 'string', 'max:100'],
            'costo_total' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.plantilla_id' => ['nullable', 'exists:plantillas_mantenimiento,id'],
            'items.*.nombre' => ['required_with:items', 'string', 'max:255'],
            'items.*.tipo_mantenimiento' => ['required_with:items', 'string', 'max:50'],
            'items.*.costo' => ['nullable', 'numeric', 'min:0'],
            'comprobante' => ['nullable', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
            'fotos' => ['nullable', 'array', 'max:5'],
            'fotos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
