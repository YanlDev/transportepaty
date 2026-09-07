<?php

namespace App\Http\Requests;

use App\Enums\MotivoTraslado;
use App\Enums\TipoCarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que hace falta para emitir una guía de transportista.
 *
 * Se valida acá lo que SUNAT rechazaría después: el ubigeo de ambos puntos, el
 * RUC del destinatario y la existencia de las unidades y el conductor en el
 * padrón —de ahí salen el TUC y la licencia, que también son obligatorios—.
 */
class EmitirGuiaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_traslado' => ['required', 'date'],

            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')],
            'destinatario' => ['required', 'string', 'max:255'],
            'destinatario_ruc' => ['required', 'string', 'regex:/^\d{11}$/'],

            'partida.ubigeo' => ['required', 'string', Rule::exists('ubigeos', 'codigo')],
            'partida.direccion' => ['required', 'string', 'max:1000'],
            'partida.nombre' => ['nullable', 'string', 'max:255'],
            'llegada.ubigeo' => ['required', 'string', Rule::exists('ubigeos', 'codigo')],
            'llegada.direccion' => ['required', 'string', 'max:1000'],
            'llegada.nombre' => ['nullable', 'string', 'max:255'],

            'tracto_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')],
            'carreta_id' => ['nullable', 'integer', Rule::exists('vehiculos', 'id')],
            'conductor_id' => ['required', 'integer', Rule::exists('conductores', 'id')],

            'peso' => ['required', 'numeric', 'gt:0'],
            'unidad_peso' => ['required', 'string', Rule::in(['KGM', 'TNE'])],
            'tipo_carga' => ['required', Rule::enum(TipoCarga::class)],
            'motivo_traslado' => ['required', Rule::enum(MotivoTraslado::class)],

            // Las guías del remitente que sustentan el traslado. Son opcionales
            // en el esquema, pero en esta operación siempre hay al menos una.
            'guias_remitente' => ['array'],
            'guias_remitente.*.numero' => ['required', 'string', 'max:30'],
            'guias_remitente.*.ruc' => ['required', 'string', 'regex:/^\d{11}$/'],

            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'partida.ubigeo.required' => 'Elige el distrito del punto de partida.',
            'llegada.ubigeo.required' => 'Elige el distrito del punto de llegada.',
            'destinatario_ruc.regex' => 'El RUC del destinatario son 11 dígitos.',
            'guias_remitente.*.ruc.regex' => 'El RUC de la guía del remitente son 11 dígitos.',
            'peso.gt' => 'El peso bruto debe ser mayor que cero.',
        ];
    }
}
