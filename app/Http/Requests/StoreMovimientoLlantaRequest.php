<?php

namespace App\Http\Requests;

use App\Enums\CondicionLlanta;
use App\Enums\MotivoRetiroLlanta;
use App\Enums\TipoMovimientoLlanta;
use App\Models\Llanta;
use App\Models\Vehiculo;
use App\Services\PosicionesLlanta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Un movimiento sobre una posición: instalar en una vacía, o rotar, reparar
 * o retirar la que está puesta. La medición de varias posiciones a la vez va
 * por `StoreMedicionLlantasRequest`.
 */
class StoreMovimientoLlantaRequest extends FormRequest
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
        $posiciones = PosicionesLlanta::codigos($this->vehiculo()->tipo);

        return [
            'tipo' => ['required', Rule::in([
                TipoMovimientoLlanta::Instalacion->value,
                TipoMovimientoLlanta::Rotacion->value,
                TipoMovimientoLlanta::Reparacion->value,
                TipoMovimientoLlanta::Retiro->value,
            ])],
            'posicion' => ['required', 'string', Rule::in($posiciones)],
            'posicion_destino' => ['nullable', 'required_if:tipo,rotacion', 'prohibited_unless:tipo,rotacion', 'string', Rule::in($posiciones), 'different:posicion'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'profundidad_mm' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'marca' => ['nullable', 'prohibited_unless:tipo,instalacion', 'string', 'max:255'],
            'medida' => ['nullable', 'prohibited_unless:tipo,instalacion', 'string', 'max:50'],
            'codigo' => ['nullable', 'prohibited_unless:tipo,instalacion', 'string', 'max:100'],
            'condicion' => ['nullable', 'required_if:tipo,instalacion', 'prohibited_unless:tipo,instalacion', Rule::enum(CondicionLlanta::class)],
            'motivo_retiro' => ['nullable', 'required_if:tipo,retiro', 'prohibited_unless:tipo,retiro', Rule::enum(MotivoRetiroLlanta::class)],
            'observacion' => ['nullable', 'required_if:tipo,reparacion', 'string', 'max:500'],
        ];
    }

    /**
     * Se instala solo en una posición vacía; lo demás, sobre la llanta que
     * está puesta.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ocupada = Llanta::query()
                    ->montadas()
                    ->where('vehiculo_id', $this->vehiculo()->id)
                    ->where('posicion', $this->string('posicion')->value())
                    ->exists();

                $posicion = $this->string('posicion')->value();

                if ($this->input('tipo') === TipoMovimientoLlanta::Instalacion->value && $ocupada) {
                    $validator->errors()->add('posicion', "La posición {$posicion} ya tiene una llanta: retírala primero.");
                }

                if ($this->input('tipo') !== TipoMovimientoLlanta::Instalacion->value && ! $ocupada) {
                    $validator->errors()->add('posicion', "La posición {$posicion} no tiene llanta.");
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
            'posicion_destino.different' => 'Elige otra posición para la rotación.',
            'observacion.required_if' => 'Anota qué se reparó.',
            'motivo_retiro.required_if' => 'Elige por qué se retira.',
            'condicion.required_if' => 'Elige si es nueva, reencauchada o usada.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }

    public function vehiculo(): Vehiculo
    {
        $vehiculo = $this->route('vehiculo');

        abort_unless($vehiculo instanceof Vehiculo, 404);

        return $vehiculo;
    }
}
