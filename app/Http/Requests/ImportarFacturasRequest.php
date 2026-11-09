<?php

namespace App\Http\Requests;

use App\Services\Archivos\ReglasDeArchivo;
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
            'archivos' => ['required', 'array', 'min:1', 'max:'.config('uploads.pdf.lote.max_archivos')],
            'archivos.*' => ReglasDeArchivo::pdfDeLote(),
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
            'archivos.*.mimetypes' => 'Solo se pueden subir archivos PDF.',
        ];
    }
}
