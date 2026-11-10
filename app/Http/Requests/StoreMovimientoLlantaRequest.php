<?php

namespace App\Http\Requests;

use App\Enums\CondicionLlanta;
use App\Enums\MotivoRetiroLlanta;
use App\Enums\OrigenLlanta;
use App\Enums\Permiso;
use App\Enums\TipoMovimientoLlanta;
use App\Models\DetalleSalida;
use App\Models\Llanta;
use App\Models\Vehiculo;
use App\Services\LlantasDeAlmacen;
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
    /**
     * Antes que nada, el permiso: quien no registra llantas no llega a ver
     * qué le falta al formulario.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Llanta::class) ?? false;
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
            // De dónde sale la llanta que se monta (ver `OrigenLlanta`).
            'origen' => ['nullable', 'required_if:tipo,instalacion', 'prohibited_unless:tipo,instalacion', Rule::enum(OrigenLlanta::class)],
            'detalle_salida_id' => ['nullable', 'required_if:origen,almacen', 'prohibited_unless:origen,almacen', 'integer', Rule::exists('detalles_salida', 'id')],
            'llanta_id' => ['nullable', 'required_if:origen,retirada', 'prohibited_unless:origen,retirada', 'integer', Rule::exists('llantas', 'id')],
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

                if ($this->input('tipo') === TipoMovimientoLlanta::Instalacion->value && ! $ocupada) {
                    $this->validarOrigen($validator);
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
            'origen.required_if' => 'Di de dónde sale la llanta: del almacén o de otra unidad.',
            'detalle_salida_id.required_if' => 'Elige el vale de almacén con que salió la llanta.',
            'llanta_id.required_if' => 'Elige cuál de las llantas retiradas se monta.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }

    /**
     * Una llanta nueva sale del almacén: se monta contra una línea de un vale
     * a nombre de esta unidad que todavía tenga llantas por montar. Una
     * retirada de otra unidad se vuelve a montar sin vale. Cargar una que ya
     * estaba puesta antes del almacén es solo para quien tiene ese permiso.
     */
    private function validarOrigen(Validator $validator): void
    {
        $origen = OrigenLlanta::from((string) $this->input('origen'));
        $llantas = app(LlantasDeAlmacen::class);

        if ($origen === OrigenLlanta::Previa) {
            if (! ($this->user()?->checkPermissionTo(Permiso::MantenimientoCargaInicial) ?? false)) {
                $validator->errors()->add('origen', 'Una llanta se monta con su vale de almacén, o viene de otra unidad. Cargar las que ya estaban puestas es solo del administrador.');
            }

            return;
        }

        if ($origen === OrigenLlanta::Retirada) {
            $llanta = Llanta::query()->find($this->integer('llanta_id'));

            if ($llanta === null || $llanta->retirada_el === null) {
                $validator->errors()->add('llanta_id', 'Esa llanta sigue montada: retírala primero de su unidad.');
            } elseif ($llanta->motivo_retiro === MotivoRetiroLlanta::Dano) {
                $validator->errors()->add('llanta_id', 'Esa llanta se retiró por daño: no se vuelve a montar.');
            }

            return;
        }

        $detalle = DetalleSalida::query()->with(['salida', 'articulo'])->find($this->integer('detalle_salida_id'));
        $salida = $detalle?->salida;

        if ($detalle === null || $salida === null || $salida->estaAnulado()) {
            $validator->errors()->add('detalle_salida_id', 'Ese vale de almacén está anulado o no existe.');
        } elseif ($salida->vehiculo_id !== $this->vehiculo()->id) {
            $validator->errors()->add('detalle_salida_id', "El vale {$salida->numero()} no salió para {$this->vehiculo()->placa}.");
        } elseif (! $llantas->esLlanta($detalle->articulo)) {
            $validator->errors()->add('detalle_salida_id', "{$detalle->articulo->nombre} no es una llanta del almacén.");
        } elseif ($llantas->disponibles($detalle) < 1) {
            $validator->errors()->add('detalle_salida_id', "Las llantas del vale {$salida->numero()} ya están todas montadas o devueltas. Saca otro vale de almacén.");
        } elseif ($this->date('fecha')?->lt($salida->fecha)) {
            $validator->errors()->add('fecha', "La llanta salió del almacén el {$salida->fecha->format('d/m/Y')}: no se pudo montar antes.");
        }
    }

    public function vehiculo(): Vehiculo
    {
        $vehiculo = $this->route('vehiculo');

        abort_unless($vehiculo instanceof Vehiculo, 404);

        return $vehiculo;
    }
}
