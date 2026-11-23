<?php

namespace App\Http\Requests;

use App\Enums\CondicionLlanta;
use App\Models\DetalleIngreso;
use App\Models\Llanta;
use App\Services\LlantasDeAlmacen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Los códigos de las llantas de una línea de recepción: uno por llanta, sin
 * repetir entre ellos ni con otra llanta, y no más que las que entraron.
 */
class CodificarLlantasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Llanta::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'detalle_ingreso_id' => ['required', 'integer', Rule::exists('detalles_ingreso', 'id')],
            'condicion' => ['required', Rule::enum(CondicionLlanta::class)],
            'medida' => ['nullable', 'string', 'max:50'],
            'codigos' => ['required', 'array', 'min:1', 'max:200'],
            'codigos.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $codigos = $this->codigos();
                $vistos = [];

                foreach ($codigos as $indice => $codigo) {
                    $clave = mb_strtolower($codigo);

                    if (isset($vistos[$clave])) {
                        $validator->errors()->add("codigos.{$indice}", "El código {$codigo} está dos veces.");
                    } elseif (Llanta::query()->whereRaw('lower(codigo) = ?', [$clave])->exists()) {
                        $validator->errors()->add("codigos.{$indice}", "Ya hay una llanta con el código {$codigo}.");
                    }

                    $vistos[$clave] = true;
                }

                $linea = DetalleIngreso::query()->with(['ingreso', 'articulo'])->find($this->integer('detalle_ingreso_id'));
                $llantas = app(LlantasDeAlmacen::class);

                if ($linea === null || $linea->ingreso->estaAnulado() || ! $llantas->esLlanta($linea->articulo)) {
                    $validator->errors()->add('detalle_ingreso_id', 'Esa línea no es de una recepción de llantas vigente.');
                } elseif (count($codigos) > $llantas->faltanCodificar($linea)) {
                    $validator->errors()->add('codigos', "De esa recepción solo faltan codificar {$llantas->faltanCodificar($linea)}.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigos.required' => 'Escribe al menos un código.',
            'codigos.*.required' => 'Falta el código de esta llanta.',
        ];
    }

    /**
     * Los códigos sin espacios sobrantes, en el orden en que llegaron.
     *
     * @return array<int, string>
     */
    public function codigos(): array
    {
        return array_map(fn (mixed $codigo): string => trim((string) $codigo), (array) $this->input('codigos', []));
    }
}
