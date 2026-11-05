<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Poner una GR en un ticket: quien asigna elige el ticket y la parada (o
 * que entre como parada no programada, en el tramo que diga). El ticket
 * tiene que ser de la misma unidad que la GR y estar abierto.
 */
class AsignarGuiaRequest extends FormRequest
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
            'vuelta_id' => ['required', Rule::exists('vueltas', 'id')],
            'parada_vuelta_id' => ['nullable', Rule::exists('paradas_vuelta', 'id')],
            'sentido' => ['required_without:parada_vuelta_id', 'nullable', Rule::enum(SentidoTramo::class)],
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

                $guia = $this->guia();
                $vuelta = $this->vuelta();

                if ($guia->parada_vuelta_id !== null) {
                    $validator->errors()->add('vuelta_id', "La GR {$guia->numero_gr} ya está en un ticket: sácala antes de asignarla a otro.");

                    return;
                }

                if ($guia->tracto_id === null) {
                    $validator->errors()->add('vuelta_id', "La GR {$guia->numero_gr} no tiene una unidad reconocida: corrige la placa en Guías.");

                    return;
                }

                if ($vuelta->tracto_id !== $guia->tracto_id) {
                    $validator->errors()->add('vuelta_id', "El ticket {$vuelta->codigo} es de {$vuelta->tracto->placa} y la GR es de {$guia->placa_tracto}.");

                    return;
                }

                if (! $vuelta->estaAbierta()) {
                    $validator->errors()->add('vuelta_id', "El ticket {$vuelta->codigo} está cerrado: reábrelo para asignarle una GR.");

                    return;
                }

                $parada = $this->parada();

                if ($parada !== null && $parada->vuelta_id !== $vuelta->id) {
                    $validator->errors()->add('parada_vuelta_id', "Esa parada no es del ticket {$vuelta->codigo}.");
                } elseif ($parada?->esVacio()) {
                    $validator->errors()->add('parada_vuelta_id', 'Un tramo vacío no lleva GR: elige una parada con carga.');
                }
            },
        ];
    }

    public function guia(): Viaje
    {
        /** @var Viaje $guia */
        $guia = $this->route('viaje');

        return $guia;
    }

    public function vuelta(): Vuelta
    {
        return Vuelta::query()->with('tracto:id,placa')->findOrFail($this->integer('vuelta_id'));
    }

    public function parada(): ?ParadaVuelta
    {
        return $this->filled('parada_vuelta_id')
            ? ParadaVuelta::query()->findOrFail($this->integer('parada_vuelta_id'))
            : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vuelta_id' => 'ticket',
            'parada_vuelta_id' => 'parada',
            'sentido' => 'tramo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sentido.required_without' => 'Elige la parada o, si entra como no programada, en qué tramo va.',
        ];
    }
}
