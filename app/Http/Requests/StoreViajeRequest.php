<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreViajeRequest extends FormRequest
{
    /**
     * Cuántas GR entran por lote y cuánto puede pesar cada una.
     *
     * Van atados a lo que aguanta PHP (`max_file_uploads`,
     * `upload_max_filesize`) y no a un número cómodo: pasado ese punto el
     * servidor descarta el cuerpo entero antes de que Laravel lo vea, y el
     * usuario recibía «archivos es obligatorio» —como si no hubiera adjuntado
     * nada— en vez de un aviso de tamaño. Validando por debajo del límite, el
     * mensaje dice lo que pasó.
     */
    private const MAXIMO_ARCHIVOS = 20;

    private const MAXIMO_KB_POR_ARCHIVO = 2048;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archivos' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_ARCHIVOS],
            'archivos.*' => ['file', 'mimes:pdf', 'max:'.self::MAXIMO_KB_POR_ARCHIVO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.max' => 'Se pueden subir hasta :max GR por vez. Divide el lote.',
            'archivos.*.max' => 'Cada GR debe pesar menos de 2 MB.',
        ];
    }
}
