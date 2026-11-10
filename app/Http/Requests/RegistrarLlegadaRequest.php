<?php

namespace App\Http\Requests;

use App\Enums\Permiso;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\OdometroDeUnidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * La llegada de la unidad a la base: el día (no antes de su última GR ni
 * en el futuro) y el kilometraje, que no puede retroceder.
 */
class RegistrarLlegadaRequest extends FormRequest
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
            'llegada_el' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addDay()->toDateString()],
            'km_llegada' => ['nullable', 'integer', 'min:1', 'max:9999999'],
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

                $ultimaGuia = Viaje::query()
                    ->whereIn('parada_vuelta_id', $vuelta->paradas()->select('id'))
                    ->max('fecha_traslado');

                if ($ultimaGuia !== null && $this->date('llegada_el')?->lt(Carbon::parse($ultimaGuia)->startOfDay())) {
                    $validator->errors()->add('llegada_el', 'La última GR del ticket es del '.Carbon::parse($ultimaGuia)->format('d/m').': no pudo llegar antes.');
                }

                $kmNuevo = $this->filled('km_llegada') ? $this->integer('km_llegada') : null;

                // Anotar el km que faltaba es de quien registra la llegada;
                // cambiar o quitar uno ya anotado es una corrección.
                if ($vuelta->km_llegada !== null && $kmNuevo !== $vuelta->km_llegada
                    && ! ($this->user()?->checkPermissionTo(Permiso::MantenimientoCorregirKm) ?? false)) {
                    $validator->errors()->add('km_llegada', 'El km de llegada ya está anotado ('.number_format($vuelta->km_llegada, 0, '.', ',').'): corregirlo lo hace quien tiene ese permiso.');

                    return;
                }

                if ($kmNuevo !== null && $kmNuevo !== $vuelta->km_llegada) {
                    $error = app(OdometroDeUnidad::class)->error($vuelta->tracto, $kmNuevo, OdometroDeUnidad::deLlegada($vuelta->id), $this->date('llegada_el'));

                    if ($error !== null) {
                        $validator->errors()->add('km_llegada', $error);
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
            'llegada_el' => 'día de llegada',
            'km_llegada' => 'kilometraje',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'llegada_el.before_or_equal' => 'La llegada no puede ser futura.',
        ];
    }
}
