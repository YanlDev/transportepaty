<?php

namespace App\Http\Requests;

use App\Enums\NivelMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El plan de una marca entero, de una vez: sus niveles con sus intervalos y
 * lo que lleva cada uno.
 *
 * Los niveles van en orden desde la X sin saltarse ninguno, cada uno con más
 * km que el anterior, porque cada uno incluye a los de abajo. Un nivel que ya
 * tiene órdenes no se puede quitar: perderían a qué servicio pertenecen.
 */
class UpdatePlanMantenimientoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'servicios' => ['required', 'array', 'min:1', 'max:'.count(NivelMantenimiento::cases())],
            'servicios.*.codigo' => ['required', 'distinct', Rule::enum(NivelMantenimiento::class)],
            'servicios.*.nombre' => ['required', 'string', 'max:255'],
            'servicios.*.intervalo_km' => ['required', 'integer', 'min:1000', 'max:2000000'],
            'servicios.*.intervalo_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'servicios.*.repuestos' => ['present', 'array', 'max:100'],
            'servicios.*.repuestos.*.descripcion' => ['required', 'string', 'max:255'],
            'servicios.*.repuestos.*.codigo' => ['nullable', 'string', 'max:255'],
            'servicios.*.repuestos.*.cantidad' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'servicios.*.repuestos.*.unidad' => ['nullable', 'string', 'max:20'],
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

                $this->validarNiveles($validator);
                $this->validarQuitados($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'servicios.*.intervalo_km.min' => 'El intervalo tiene que ser de al menos 1,000 km.',
            'servicios.*.repuestos.*.descripcion.required' => 'Cada ítem necesita su descripción.',
        ];
    }

    private function validarNiveles(Validator $validator): void
    {
        $servicios = array_values($this->input('servicios'));
        $anterior = null;

        foreach (NivelMantenimiento::cases() as $indice => $nivel) {
            $servicio = $servicios[$indice] ?? null;

            if ($servicio === null) {
                return;
            }

            if ($servicio['codigo'] !== $nivel->value) {
                $validator->errors()->add('servicios', 'Los niveles van en orden desde la X, sin saltarse ninguno.');

                return;
            }

            if ($anterior !== null && (int) $servicio['intervalo_km'] <= (int) $anterior['intervalo_km']) {
                $validator->errors()->add(
                    "servicios.{$indice}.intervalo_km",
                    "La {$nivel->value} tiene que ir a más km que la {$anterior['codigo']}, porque la incluye.",
                );
            }

            $anterior = $servicio;
        }
    }

    private function validarQuitados(Validator $validator): void
    {
        /** @var PlanMantenimiento $plan */
        $plan = $this->route('plan');

        $quedan = Arr::pluck($validator->validated()['servicios'], 'codigo');

        $quitadosConOrdenes = $plan->servicios()
            ->whereNotIn('codigo', $quedan)
            ->whereIn('id', OrdenTrabajo::query()->select('servicio_mantenimiento_id'))
            ->pluck('codigo');

        foreach ($quitadosConOrdenes as $codigo) {
            $validator->errors()->add(
                'servicios',
                "La {$codigo} ya tiene órdenes de trabajo registradas: no se puede quitar del plan.",
            );
        }
    }
}
