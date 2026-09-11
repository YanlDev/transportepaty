<?php

namespace App\Http\Requests;

use App\Services\CalculadoraCotizacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El desglose de una ruta que todavía no se guardó, para que el formulario
 * muestre la tarifa mientras se completa.
 *
 * Pide menos que `CotizacionRequest` a propósito: acá no se está registrando
 * una cotización, solo se está preguntando cuánto daría. Basta con lo que
 * entra en la fórmula —kilómetros, días, margen y los conceptos de ruta—, y no
 * hace falta cliente ni número.
 */
class PrevisualizarCotizacionRequest extends FormRequest
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
            'km' => ['required', 'numeric', 'min:0'],
            'dias' => ['required', 'numeric', 'min:0'],
            'margen_pct' => ['required', 'numeric', 'min:0', 'max:1'],
            // Peajes, viáticos, alojamiento, cochera, carga/descarga y otros:
            // se leen de la calculadora para que agregar un concepto nuevo no
            // exija acordarse de tocar también esta lista.
            ...array_fill_keys(
                array_keys(CalculadoraCotizacion::CONCEPTOS_RUTA),
                ['nullable', 'numeric', 'min:0'],
            ),
        ];
    }
}
