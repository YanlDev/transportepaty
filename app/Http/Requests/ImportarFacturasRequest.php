<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Subir facturas en PDF, con el botón o arrastrándolas. Los mismos límites
 * que la subida de GR, por la misma razón: van atados a lo que aguanta PHP
 * (`max_file_uploads`, `upload_max_filesize`), y validando por debajo el
 * mensaje dice lo que pasó en vez de «archivos es obligatorio».
 */
class ImportarFacturasRequest extends FormRequest
{
    private const MAXIMO_ARCHIVOS = 20;

    private const MAXIMO_KB_POR_ARCHIVO = 2048;

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
            'archivos.max' => 'Se pueden subir hasta :max facturas por vez. Divide el lote.',
            'archivos.*.max' => 'Cada factura debe pesar menos de 2 MB.',
        ];
    }
}
