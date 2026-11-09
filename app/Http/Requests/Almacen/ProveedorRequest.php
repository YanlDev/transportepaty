<?php

namespace App\Http\Requests\Almacen;

use App\Models\Proveedor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un proveedor. El RUC no es obligatorio (hay quien vende
 * sin él), pero si se escribe no se repite.
 */
class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruc' => $this->filled('ruc') ? preg_replace('/\D/', '', (string) $this->input('ruc')) : null,
            'razon_social' => mb_strtoupper(trim((string) $this->input('razon_social'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $proveedor = $this->route('proveedor');

        return [
            'ruc' => ['nullable', 'digits:11', Rule::unique('proveedores', 'ruc')->ignore($proveedor instanceof Proveedor ? $proveedor->id : null)],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'condicion_pago' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruc.digits' => 'El RUC tiene 11 dígitos.',
            'ruc.unique' => 'Ya hay un proveedor con ese RUC.',
        ];
    }
}
