<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los cinco campos de una programación. Sirve para el alta y para la
 * corrección: se programa y se corrige con exactamente el mismo formulario,
 * así que no tiene sentido partirlo en dos.
 */
class GuardarProgramacionRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            // Solo tractos: la carga particular la mueve la unidad tractora,
            // y ofrecer carretas en el desplegable solo da lugar a errores.
            'vehiculo_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', 'tracto')],
            'conductor_id' => ['required', Rule::exists('conductores', 'id')],
            'cliente_id' => ['required', Rule::exists('clientes', 'id')],
            'destino' => ['required', 'string', 'max:255'],
            // A quién más avisar de esta salida: el dueño de la unidad, un
            // apoyo. Es de la salida, no de la persona.
            'whatsapp_adicional' => ['nullable', 'string', 'max:30'],
            // El flete acordado, en soles. Opcional: no siempre hay precio
            // cerrado cuando se programa la unidad.
            'precio_flete' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            // Cómo leer ese monto: si ya trae el IGV o hay que sumárselo.
            'precio_incluye_igv' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehiculo_id' => 'unidad',
            'conductor_id' => 'conductor',
            'cliente_id' => 'cliente',
            'whatsapp_adicional' => 'WhatsApp adicional',
            'precio_flete' => 'precio del flete',
        ];
    }
}
