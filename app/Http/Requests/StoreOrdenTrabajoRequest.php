<?php

namespace App\Http\Requests;

use App\Enums\EstadoOrdenTrabajo;
use App\Enums\TipoOrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\ServicioMantenimiento;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registrar un trabajo de taller, con su panel fotográfico. Las fotos llegan
 * ya achicadas desde el celular; el límite por foto es para la que se suba
 * sin pasar por ahí.
 */
class StoreOrdenTrabajoRequest extends FormRequest
{
    private const MAXIMO_FOTOS = 12;

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
            'vehiculo_id' => ['required', 'integer', Rule::exists('vehiculos', 'id')->whereNull('deleted_at')],
            ...$this->reglasComunes(),
        ];
    }

    /**
     * Lo que comparten el alta y la edición.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasComunes(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoOrdenTrabajo::class)],
            'servicio_mantenimiento_id' => [
                'nullable',
                'required_if:tipo,'.TipoOrdenTrabajo::Preventiva->value,
                'prohibited_if:tipo,'.TipoOrdenTrabajo::Correctiva->value,
                'integer',
                Rule::exists('servicios_mantenimiento', 'id'),
            ],
            'estado' => ['required', Rule::enum(EstadoOrdenTrabajo::class)],
            'fecha' => ['required', 'date'],
            'kilometraje' => ['nullable', 'integer', 'min:1', 'max:9999999'],
            'inicio' => ['nullable', 'date'],
            'termino' => ['nullable', 'date', 'after_or_equal:inicio'],
            'trabajo' => ['required', 'string', 'max:5000'],
            'tecnico' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'fotos_antes' => ['nullable', 'array', 'max:'.self::MAXIMO_FOTOS],
            'fotos_antes.*' => ['image', 'mimes:jpeg,png,webp', 'max:10240'],
            'fotos_despues' => ['nullable', 'array', 'max:'.self::MAXIMO_FOTOS],
            'fotos_despues.*' => ['image', 'mimes:jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * El servicio tiene que ser del plan de la marca de la unidad, y una
     * preventiva que se da por finalizada necesita su km: es desde ahí que
     * se cuenta el próximo servicio.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('tipo') !== TipoOrdenTrabajo::Preventiva->value) {
                    return;
                }

                $vehiculo = $this->vehiculo();
                $plan = PlanMantenimiento::deMarca($vehiculo->marca);
                $servicio = ServicioMantenimiento::query()->find($this->integer('servicio_mantenimiento_id'));

                if ($plan === null || $servicio?->plan_mantenimiento_id !== $plan->id) {
                    $validator->errors()->add(
                        'servicio_mantenimiento_id',
                        $plan === null
                            ? 'La marca de '.$vehiculo->placa.' todavía no tiene plan de mantenimiento: regístralo como correctiva.'
                            : 'Ese servicio no es del plan '.$plan->marca.'.',
                    );

                    return;
                }

                if ($this->input('estado') === EstadoOrdenTrabajo::Finalizada->value && ! $this->filled('kilometraje')) {
                    $validator->errors()->add('kilometraje', 'Anota el km: el próximo servicio se cuenta desde acá.');
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
            'servicio_mantenimiento_id.required_if' => 'Elige el servicio del plan.',
            'fotos_antes.max' => 'Hasta :max fotos por grupo.',
            'fotos_despues.max' => 'Hasta :max fotos por grupo.',
            'fotos_antes.*.max' => 'Cada foto debe pesar menos de 10 MB.',
            'fotos_despues.*.max' => 'Cada foto debe pesar menos de 10 MB.',
            'termino.after_or_equal' => 'El término no puede ser antes del inicio.',
        ];
    }

    protected function vehiculo(): Vehiculo
    {
        return Vehiculo::query()->withTrashed()->findOrFail($this->integer('vehiculo_id'));
    }
}
