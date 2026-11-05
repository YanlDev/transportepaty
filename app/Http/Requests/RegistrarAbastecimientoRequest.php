<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\Abastecimiento;
use App\Models\Vuelta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El combustible que abastecimiento autoriza (el QR de Repsol, o Kio). Nada
 * se deduce: el ticket y el tramo los elige quien registra, el ticket tiene
 * que ser de esa unidad y estar listo (OK de taller, tarjeta confirmada), y
 * lo que pase del tope de la tarjeta necesita motivo y permiso.
 */
class RegistrarAbastecimientoRequest extends FormRequest
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
            'vehiculo_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value)],
            'vuelta_id' => ['required', Rule::exists('vueltas', 'id')],
            'sentido' => ['required', Rule::enum(SentidoTramo::class)],
            'punto_abastecimiento_id' => ['nullable', Rule::exists('puntos_abastecimiento', 'id')],
            // Un grifo fuera de la lista, en un caso excepcional: o uno o el otro.
            'punto_libre' => ['nullable', 'string', 'max:80', 'prohibits:punto_abastecimiento_id'],
            'fecha' => ['required', 'date'],
            'galones' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'motivo_exceso' => ['nullable', 'string', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:500'],
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

                $vuelta = $this->vuelta();

                if ($vuelta->tracto_id !== $this->integer('vehiculo_id')) {
                    $validator->errors()->add('vuelta_id', "El ticket {$vuelta->codigo} es de la unidad {$vuelta->tracto->placa}, no de esta.");

                    return;
                }

                if (($bloqueo = $vuelta->bloqueoDeGastos()) !== null) {
                    $validator->errors()->add('vuelta_id', $bloqueo);

                    return;
                }

                $sentido = SentidoTramo::from($this->string('sentido')->toString());
                $dotacion = $vuelta->dotacionDe($sentido);

                if ($dotacion === null) {
                    $validator->errors()->add('sentido', "Primero confirma la tarjeta de la {$sentido->label()} del ticket {$vuelta->codigo}: es lo que se autoriza.");

                    return;
                }

                $tope = $dotacion->tope();

                if ($tope === null) {
                    $validator->errors()->add('sentido', "La tarjeta «{$dotacion->nombre}» tiene galones por definir: complétala en la pizarra y vuelve a confirmarla.");

                    return;
                }

                $autorizado = (float) Abastecimiento::query()
                    ->vigentes()
                    ->where('vuelta_id', $vuelta->id)
                    ->where('sentido', $sentido)
                    ->sum('galones');

                if ($autorizado + $this->float('galones') <= $tope + 0.001) {
                    return;
                }

                $disponible = max(0.0, $tope - $autorizado);

                if (! $this->user()?->can('excederTope', Abastecimiento::class)) {
                    $validator->errors()->add('galones', 'Pasa el tope de la tarjeta ('.$this->galones($tope).' gal; quedan '.$this->galones($disponible).'): solo quien tiene permiso puede autorizar más.');
                } elseif (blank($this->input('motivo_exceso'))) {
                    $validator->errors()->add('motivo_exceso', 'Pasa el tope de la tarjeta ('.$this->galones($tope).' gal; quedan '.$this->galones($disponible).'): escribe por qué.');
                }
            },
        ];
    }

    public function vuelta(): Vuelta
    {
        return Vuelta::query()->with(['tracto:id,placa', 'dotaciones', 'revision'])->findOrFail($this->integer('vuelta_id'));
    }

    private function galones(float $galones): string
    {
        return rtrim(rtrim(number_format($galones, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehiculo_id' => 'unidad',
            'vuelta_id' => 'ticket',
            'sentido' => 'tramo',
            'punto_abastecimiento_id' => 'punto',
            'punto_libre' => 'punto fuera de la lista',
            'motivo_exceso' => 'motivo',
        ];
    }
}
