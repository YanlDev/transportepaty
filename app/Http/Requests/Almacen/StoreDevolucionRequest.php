<?php

namespace App\Http\Requests\Almacen;

use App\Models\DetalleSalida;
use App\Models\Llanta;
use App\Models\SalidaAlmacen;
use App\Rules\FechaDelAlmacen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Lo que no se usó y vuelve al almacén, línea por línea del vale. No puede
 * volver más de lo que salió (menos lo ya devuelto).
 */
class StoreDevolucionRequest extends FormRequest
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
            'fecha' => ['required', 'date', new FechaDelAlmacen],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.detalle_salida_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $salida = $this->route('salida');

                if (! $salida instanceof SalidaAlmacen || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->date('fecha')?->lt($salida->fecha)) {
                    $validator->errors()->add('fecha', 'La devolución no puede ser anterior al vale.');
                }

                $lineas = $salida->detalles()->get()->keyBy('id');

                foreach ($this->input('detalles', []) as $i => $linea) {
                    $detalle = $lineas->get((int) $linea['detalle_salida_id']);

                    if (! $detalle instanceof DetalleSalida) {
                        $validator->errors()->add("detalles.{$i}.detalle_salida_id", 'Esa línea no es de este vale.');

                        continue;
                    }

                    // Una llanta ya montada no puede volver al almacén.
                    $montadas = Llanta::query()->where('detalle_salida_id', $detalle->id)->count();
                    $pendiente = round((float) $detalle->cantidad - $detalle->cantidadDevuelta() - $montadas, 3);

                    if ((float) $linea['cantidad'] - $pendiente > 0.0005) {
                        $validator->errors()->add("detalles.{$i}.cantidad", $montadas > 0 ? "Solo pueden volver {$pendiente}: {$montadas} ya están montadas." : "Solo pueden volver {$pendiente}.");
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detalles.required' => 'Anota al menos una cantidad que vuelve.',
        ];
    }
}
