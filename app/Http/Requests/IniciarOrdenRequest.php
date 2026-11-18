<?php

namespace App\Http\Requests;

use App\Models\OrdenTrabajo;
use App\Services\Archivos\ReglasDeArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El mecánico empieza una orden: sube las fotos de cómo llegó la unidad y,
 * si lo tiene a la vista, el km. Que haya al menos una foto lo exige
 * `EjecucionDeOrden`, contando también las que la orden ya traía.
 */
class IniciarOrdenRequest extends FormRequest
{
    public const MAXIMO_FOTOS = 12;

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
            'fotos' => ['nullable', 'array', 'max:'.$this->cupoDeFotos('antes')],
            'fotos.*' => ReglasDeArchivo::imagen(),
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fotos.max' => 'La orden admite hasta '.self::MAXIMO_FOTOS.' fotos por grupo.',
            'fotos.*.max' => 'Cada foto debe pesar menos de 10 MB.',
            'fotos.*.mimetypes' => 'Cada foto debe ser JPG, PNG, WEBP o HEIC.',
        ];
    }

    /**
     * Cuántas fotos más entran en un grupo de la orden.
     */
    protected function cupoDeFotos(string $coleccion): int
    {
        /** @var OrdenTrabajo $orden */
        $orden = $this->route('orden');

        return max(0, self::MAXIMO_FOTOS - $orden->getMedia($coleccion)->count());
    }
}
