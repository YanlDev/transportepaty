<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\ZonaRuta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Una ruta del catálogo: su recorrido, su dotación y sus variantes por peso.
 * Cada línea dice a qué variante pertenece por su posición en la lista de
 * variantes (o a ninguna: vale para toda la ruta).
 */
class GuardarRutaRequest extends FormRequest
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
        $combustible = TipoLineaAbastecimiento::Combustible->value;

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'sentido' => ['nullable', Rule::enum(SentidoTramo::class)],
            'es_complemento' => ['boolean'],
            'dias_viaje' => ['nullable', 'integer', 'min:0', 'max:30'],
            'km_estandar' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'color' => ['nullable', 'string', 'max:20'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'activa' => ['boolean'],

            'paradas' => ['array', 'max:8'],
            'paradas.*.zona_origen' => ['required', Rule::enum(ZonaRuta::class)],
            'paradas.*.zona_destino' => ['nullable', Rule::enum(ZonaRuta::class)],
            'paradas.*.tipo_carga' => ['required', Rule::enum(TipoCarga::class)],
            'paradas.*.lugar_origen' => ['nullable', 'string', 'max:60'],
            'paradas.*.lugar_destino' => ['nullable', 'string', 'max:60'],

            'variantes' => ['array', 'max:12'],
            'variantes.*.id' => ['nullable', 'integer'],
            'variantes.*.nombre' => ['required', 'string', 'max:60'],
            'variantes.*.peso_desde' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'variantes.*.peso_hasta' => ['nullable', 'numeric', 'min:0', 'max:99'],

            'lineas' => ['array', 'max:40'],
            'lineas.*.id' => ['nullable', 'integer'],
            'lineas.*.variante' => ['nullable', 'integer', 'min:0'],
            'lineas.*.tipo' => ['required', Rule::enum(TipoLineaAbastecimiento::class)],
            'lineas.*.concepto' => ['required', 'string', 'max:100'],
            'lineas.*.punto_abastecimiento_id' => ['nullable', Rule::exists('puntos_abastecimiento', 'id')],
            // Sin galones: «por definir», y el tramo no tiene tope hasta
            // que alguien los ponga.
            'lineas.*.galones' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'lineas.*.tanque_lleno' => ['boolean'],
            'lineas.*.monto' => ['nullable', 'required_unless:lineas.*.tipo,'.$combustible, 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $variantes = (array) $this->input('variantes', []);

                foreach ((array) $this->input('lineas', []) as $indice => $linea) {
                    if (isset($linea['variante']) && ! array_key_exists((int) $linea['variante'], $variantes)) {
                        $validator->errors()->add("lineas.{$indice}.variante", 'Esa variante no existe.');
                    }
                }

                foreach ($variantes as $indice => $variante) {
                    if (isset($variante['peso_desde'], $variante['peso_hasta']) && (float) $variante['peso_hasta'] <= (float) $variante['peso_desde']) {
                        $validator->errors()->add("variantes.{$indice}.peso_hasta", 'Tiene que ser mayor que «más de».');
                    }
                }

                if (! $this->boolean('es_complemento') && (array) $this->input('paradas', []) === []) {
                    $validator->errors()->add('paradas', 'Una ruta principal necesita al menos una parada: es el recorrido del tramo.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'paradas.*.zona_origen' => 'desde',
            'paradas.*.tipo_carga' => 'carga',
            'variantes.*.nombre' => 'nombre de la variante',
            'lineas.*.concepto' => 'concepto',
            'lineas.*.galones' => 'galones',
            'lineas.*.monto' => 'monto',
            'dias_viaje' => 'días de viaje',
            'km_estandar' => 'km',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.*.monto.required_unless' => 'Escribe el monto del viático o del peaje.',
        ];
    }
}
