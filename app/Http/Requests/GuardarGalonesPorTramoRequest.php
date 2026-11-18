<?php

namespace App\Http\Requests;

use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La tabla de galones por tramo entera: cada fila, desde → hasta con sus
 * galones cargado y vacío (vacíos = por definir). Un tramo va una sola vez
 * por carga: la general (sin carga) y, si hace falta, una por cada carga que
 * gaste distinto.
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
            'filas.*.tipo_carga' => ['nullable', Rule::enum(TipoCarga::class)->except(TipoCarga::excluidosDeViaje())],
            'filas.*.galones_cargado' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'filas.*.galones_vacio' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'filas.*.nota' => ['nullable', 'string', 'max:200'],
            // Cargado según el peso: más de «desde» y hasta «hasta» toneladas.
            'filas.*.bandas' => ['nullable', 'array', 'max:12'],
            'filas.*.bandas.*.peso_desde' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'filas.*.bandas.*.peso_hasta' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'filas.*.bandas.*.galones' => ['required', 'numeric', 'min:0', 'max:999'],
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
                    $clave = ($fila['zona_origen'] ?? '').'>'.($fila['zona_destino'] ?? '').'>'.($fila['tipo_carga'] ?? '');

                    if (isset($vistos[$clave])) {
                        $validator->errors()->add("filas.{$indice}.zona_destino", filled($fila['tipo_carga'] ?? null)
                            ? 'Ese tramo ya está en la tabla para esa carga.'
                            : 'Ese tramo ya está en la tabla.');
                    }

                    $vistos[$clave] = true;

                    if (($fila['zona_origen'] ?? null) !== null && ($fila['zona_origen'] ?? null) === ($fila['zona_destino'] ?? null)) {
                        $validator->errors()->add("filas.{$indice}.zona_destino", 'Un tramo va de una zona a otra.');
                    }

                    // Las bandas van en orden y pegadas: cada una empieza
                    // donde termina la anterior, y solo la última queda abierta.
                    $bandas = array_values((array) ($fila['bandas'] ?? []));

                    foreach ($bandas as $posicion => $banda) {
                        $desde = $banda['peso_desde'] ?? null;
                        $hasta = $banda['peso_hasta'] ?? null;
                        $anterior = $bandas[$posicion - 1]['peso_hasta'] ?? null;
                        $ultima = $posicion === count($bandas) - 1;

                        if ($hasta === null && ! $ultima) {
                            $validator->errors()->add("filas.{$indice}.bandas", 'Solo la última banda puede quedar sin «hasta».');
                        } elseif ($hasta !== null && $desde !== null && (float) $hasta <= (float) $desde) {
                            $validator->errors()->add("filas.{$indice}.bandas", 'En cada banda, «hasta» tiene que ser mayor que «más de».');
                        } elseif ($posicion > 0 && ($desde === null || $anterior === null || abs((float) $desde - (float) $anterior) > 0.005)) {
                            $validator->errors()->add("filas.{$indice}.bandas", 'Cada banda empieza donde termina la anterior.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * Las cargas que pueden tener su propia fila: las de un viaje.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function cargas(): array
    {
        return TipoCarga::opcionesDeViaje();
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
            'filas.*.bandas.*.galones' => 'galones de la banda',
        ];
    }
}
