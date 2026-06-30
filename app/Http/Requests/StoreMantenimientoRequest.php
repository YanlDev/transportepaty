<?php

namespace App\Http\Requests;

use App\Models\Mantenimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMantenimientoRequest extends FormRequest
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
            'fecha_realizado' => ['required', 'date'],
            'odometro' => ['required', 'integer', 'min:0'],
            'proveedor' => ['nullable', 'string', 'max:255'],
            'factura_numero' => ['nullable', 'string', 'max:100'],
            'costo_total' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.plantilla_id' => ['nullable', 'exists:plantillas_mantenimiento,id'],
            'items.*.nombre' => ['required', 'string', 'max:255'],
            'items.*.tipo_mantenimiento' => ['required', 'string', 'max:50'],
            'items.*.costo' => ['nullable', 'numeric', 'min:0'],
            'comprobante' => ['nullable', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
            'fotos' => ['nullable', 'array', 'max:5'],
            'fotos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vehiculo = $this->route('vehiculo');
            $odometroMaximo = max(
                $vehiculo->kilometraje,
                Mantenimiento::where('vehiculo_id', $vehiculo->id)->max('odometro') ?? 0,
            );

            if ((int) $this->input('odometro') < $odometroMaximo) {
                $validator->errors()->add(
                    'odometro',
                    "El odómetro ({$this->input('odometro')}) es menor al último registro conocido ($odometroMaximo).",
                );
            }
        });
    }
}
