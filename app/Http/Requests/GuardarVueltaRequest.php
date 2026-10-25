<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Programar una vuelta o corregirla en el camino: la unidad, quién la
 * maneja, el día de salida y la lista de paradas de la ida y del regreso.
 * Las paradas que ya tienen GR viajan con su id y no se pueden cambiar.
 */
class GuardarVueltaRequest extends FormRequest
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
            'tracto_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value)],
            'carreta_id' => ['nullable', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Carreta->value)],
            'conductor_id' => ['nullable', Rule::exists('conductores', 'id')],
            'inicio' => ['required', 'date'],
            'paradas' => ['required', 'array', 'min:1', 'max:12'],
            'paradas.*.id' => ['nullable', 'integer'],
            'paradas.*.sentido' => ['required', Rule::enum(SentidoTramo::class)],
            'paradas.*.zona_origen' => ['required', Rule::enum(ZonaRuta::class)],
            'paradas.*.zona_destino' => ['required', Rule::enum(ZonaRuta::class)],
            'paradas.*.tipo_carga' => ['required', Rule::enum(TipoCarga::class)->except([TipoCarga::Sacos])],
            'paradas.*.cliente_id' => ['nullable', Rule::exists('clientes', 'id')],
        ];
    }

    /**
     * Lo validado, con cada campo en su tipo.
     *
     * @return array{tracto_id: int, carreta_id: int|null, conductor_id: int|null, inicio: string, paradas: list<array<string, mixed>>}
     */
    public function datos(): array
    {
        return [
            'tracto_id' => $this->integer('tracto_id'),
            'carreta_id' => $this->filled('carreta_id') ? $this->integer('carreta_id') : null,
            'conductor_id' => $this->filled('conductor_id') ? $this->integer('conductor_id') : null,
            'inicio' => $this->string('inicio')->toString(),
            'paradas' => array_values($this->validated('paradas')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tracto_id' => 'unidad',
            'carreta_id' => 'carreta',
            'conductor_id' => 'conductor',
            'inicio' => 'día de salida',
            'paradas.*.zona_origen' => 'origen',
            'paradas.*.zona_destino' => 'destino',
            'paradas.*.tipo_carga' => 'carga',
            'paradas.*.cliente_id' => 'cliente',
        ];
    }
}
