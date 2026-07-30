<?php

namespace App\Http\Requests;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Models\Asignacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

abstract class AsignacionRequest extends FormRequest
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
            'conductor_id' => [
                'required',
                $this->reglaConductorAsignable(),
                $this->reglaSinOtraAsignacionVigente('conductor_id'),
            ],
            'tracto_id' => [
                'required',
                $this->reglaVehiculoDelTipo(TipoVehiculo::Tracto),
                $this->reglaSinOtraAsignacionVigente('tracto_id'),
            ],
            'carreta_id' => [
                'nullable',
                $this->reglaVehiculoDelTipo(TipoVehiculo::Carreta),
                $this->reglaSinOtraAsignacionVigente('carreta_id'),
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conductor_id.unique' => 'Ese conductor ya tiene una unidad asignada. Libérala primero para reasignarlo.',
            'tracto_id.unique' => 'Ese tracto ya está asignado a otro conductor. Libéralo primero para reasignarlo.',
            'carreta_id.unique' => 'Esa carreta ya está asignada a otra unidad. Libérala primero para reasignarla.',
            'conductor_id.exists' => 'El conductor seleccionado no existe o está inactivo.',
            'tracto_id.exists' => 'El vehículo seleccionado no es un tracto disponible.',
            'carreta_id.exists' => 'El vehículo seleccionado no es una carreta disponible.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'conductor_id' => 'conductor',
            'tracto_id' => 'tracto',
            'carreta_id' => 'carreta',
            'desde' => 'fecha de inicio',
        ];
    }

    /**
     * Solo se asignan conductores activos. Al editar, el conductor que ya está
     * en la asignación sigue siendo válido aunque haya pasado a inactivo: que
     * su estado haya cambiado no puede impedir corregirle la carreta.
     */
    protected function reglaConductorAsignable(): Exists
    {
        $actual = $this->asignacionEnEdicion()?->conductor_id;

        return Rule::exists('conductores', 'id')->where(function (Builder $query) use ($actual): void {
            $query->where('activo', true);

            if ($actual !== null) {
                $query->orWhere('id', $actual);
            }
        });
    }

    /**
     * Un vehículo dado de baja, borrado o de otro tipo no puede ocupar la
     * ranura: una carreta no arrastra a nadie y un tracto no se remolca. El
     * fierro que la asignación ya tiene sigue siendo válido al editar.
     */
    protected function reglaVehiculoDelTipo(TipoVehiculo $tipo): Exists
    {
        $asignacion = $this->asignacionEnEdicion();
        $actual = $tipo === TipoVehiculo::Tracto
            ? $asignacion?->tracto_id
            : $asignacion?->carreta_id;

        return Rule::exists('vehiculos', 'id')
            ->where('tipo', $tipo->value)
            ->where(function (Builder $query) use ($actual): void {
                $query->where(function (Builder $query): void {
                    $query->whereIn('estado', EstadoVehiculo::asignables())
                        ->whereNull('deleted_at');
                });

                if ($actual !== null) {
                    $query->orWhere('id', $actual);
                }
            });
    }

    /**
     * Impide que el mismo conductor, tracto o carreta figure en dos asignaciones
     * vigentes. Duplica a propósito los índices únicos parciales de la tabla:
     * estos devuelven un mensaje entendible antes de que la base de datos
     * rechace el insert.
     */
    protected function reglaSinOtraAsignacionVigente(string $columna): Unique
    {
        return Rule::unique('asignaciones', $columna)
            ->whereNull('hasta')
            ->ignore($this->asignacionEnEdicion());
    }

    /**
     * La asignación que se está editando, o null al crear.
     */
    abstract protected function asignacionEnEdicion(): ?Asignacion;
}
