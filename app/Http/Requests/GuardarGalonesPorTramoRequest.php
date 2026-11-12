<?php

namespace App\Http\Requests;

use App\Enums\ZonaRuta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La tabla de galones por tramo entera: cada fila, desde → hasta con sus
 * galones cargado y vacío (vacíos = por definir). Un tramo va una sola vez.
 */
class GuardarGalonesPorTramoRequest extends FormRequest
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
            'filas' => ['present', 'array', 'max:80'],
            'filas.*.zona_origen' => ['required', Rule::enum(ZonaRuta::class)],
            'filas.*.zona_destino' => ['required', Rule::enum(ZonaRuta::class)],
            'filas.*.galones_cargado' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'filas.*.galones_vacio' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'filas.*.nota' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $vistos = [];

                foreach ((array) $this->input('filas', []) as $indice => $fila) {
                    $clave = ($fila['zona_origen'] ?? '').'>'.($fila['zona_destino'] ?? '');

                    if (isset($vistos[$clave])) {
                        $validator->errors()->add("filas.{$indice}.zona_destino", 'Ese tramo ya está en la tabla.');
                    }

                    $vistos[$clave] = true;

                    if (($fila['zona_origen'] ?? null) !== null && ($fila['zona_origen'] ?? null) === ($fila['zona_destino'] ?? null)) {
                        $validator->errors()->add("filas.{$indice}.zona_destino", 'Un tramo va de una zona a otra.');
                    }
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
            'filas.*.zona_origen' => 'desde',
            'filas.*.zona_destino' => 'hasta',
            'filas.*.galones_cargado' => 'galones cargado',
            'filas.*.galones_vacio' => 'galones vacío',
        ];
    }
}
