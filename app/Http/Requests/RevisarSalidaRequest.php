<?php

namespace App\Http\Requests;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\ResultadoTaller;
use App\Models\OrdenTrabajo;
use App\Models\Vuelta;
use App\Services\OdometroDeUnidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El apto o no apto de taller. Si no es apta hay que decir por qué y cuándo
 * estará lista, para que programación sepa a qué atenerse.
 */
class RevisarSalidaRequest extends FormRequest
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
        $noApta = Rule::requiredIf($this->input('resultado') === ResultadoTaller::NoApto->value);

        return [
            'resultado' => ['required', Rule::enum(ResultadoTaller::class)],
            'disponible_estimado' => [$noApta, 'nullable', 'date'],
            'motivo' => [$noApta, 'nullable', 'string', 'max:500'],
            'trabajos' => ['nullable', 'string', 'max:2000'],
            // El km con que sale: con el de la llegada, el ticket sabe cuánto
            // recorrió y cuánto rindió el combustible.
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Vuelta $vuelta */
                $vuelta = $this->route('vuelta');

                if ($this->input('resultado') === ResultadoTaller::Apto->value && $this->tieneOrdenAbierta($validator, $vuelta)) {
                    return;
                }

                if (! $this->filled('kilometraje')) {
                    return;
                }

                $error = app(OdometroDeUnidad::class)->error($vuelta->tracto, $this->integer('kilometraje'), OdometroDeUnidad::deSalida($vuelta->id));

                if ($error !== null) {
                    $validator->errors()->add('kilometraje', $error);
                }
            },
        ];
    }

    /**
     * Una unidad con un trabajo de taller sin terminar no sale: primero se
     * finaliza (o se anula) su orden, o el ticket queda como no apto.
     */
    private function tieneOrdenAbierta(Validator $validator, Vuelta $vuelta): bool
    {
        $abiertas = OrdenTrabajo::query()
            ->vigentes()
            ->with('vehiculo:id,placa')
            ->whereIn('vehiculo_id', array_filter([$vuelta->tracto_id, $vuelta->carreta_id]))
            ->where('estado', '!=', EstadoOrdenTrabajo::Finalizada)
            ->orderBy('id')
            ->get();

        if ($abiertas->isEmpty()) {
            return false;
        }

        $validator->errors()->add(
            'resultado',
            'No se puede dar el apto: '
                .$abiertas
                    ->map(fn (OrdenTrabajo $orden): string => "la {$orden->numero()} de {$orden->vehiculo->placa} está ".mb_strtolower($orden->estado->label()))
                    ->implode('; ')
                .'. Finaliza la orden o marca la unidad como no apta.',
        );

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'disponible_estimado' => 'fecha y hora estimada',
            'trabajos' => 'trabajos',
            'kilometraje' => 'kilometraje de salida',
        ];
    }
}
