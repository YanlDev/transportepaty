<?php

namespace App\Http\Requests;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'tracto_id' => ['required', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Tracto->value), $this->vigente('tracto_id')],
            'carreta_id' => ['nullable', Rule::exists('vehiculos', 'id')->where('tipo', TipoVehiculo::Carreta->value), $this->vigente('carreta_id')],
            'conductor_id' => ['nullable', Rule::exists('conductores', 'id'), $this->vigente('conductor_id')],
            'inicio' => ['required', 'date_format:Y-m-d'],
            'paradas' => ['required', 'array', 'min:1', 'max:12'],
            'paradas.*.id' => ['nullable', 'integer', 'distinct'],
            'paradas.*.sentido' => ['required', Rule::enum(SentidoTramo::class)],
            'paradas.*.zona_origen' => ['required', Rule::enum(ZonaRuta::class)],
            'paradas.*.zona_destino' => ['required', Rule::enum(ZonaRuta::class)],
            'paradas.*.tipo_carga' => ['required', Rule::enum(TipoCarga::class)->except([TipoCarga::Sacos])],
            'paradas.*.cliente_id' => ['nullable', Rule::exists('clientes', 'id')],
            // El lugar exacto cuando la zona no alcanza: «Tacna», «Ilo».
            'paradas.*.lugar_origen' => ['nullable', 'string', 'max:100'],
            'paradas.*.lugar_destino' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Una unidad, carreta o conductor dado de baja no se elige para un ticket;
     * el que el ticket ya tenía se puede dejar como está.
     */
    private function vigente(string $campo): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($campo): void {
            $vuelta = $this->vueltaEditada();

            if ($valor === null || ($vuelta !== null && (int) $vuelta->getAttribute($campo) === (int) $valor)) {
                return;
            }

            $vigente = $campo === 'conductor_id'
                ? Conductor::query()->whereKey($valor)->where('activo', true)->exists()
                : Vehiculo::query()->whereKey($valor)->where('estado', EstadoVehiculo::Activo)->exists();

            if (! $vigente) {
                $fallar(match ($campo) {
                    'tracto_id' => 'La unidad no está operativa.',
                    'carreta_id' => 'La carreta no está operativa.',
                    default => 'El conductor no está activo.',
                });
            }
        };
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['tracto_id', 'inicio'])) {
                    return;
                }

                // Una parada que no se mueve. En la misma zona vale si los
                // lugares difieren: una particular de Arequipa a Tacna (las
                // dos en el Sur).
                foreach ((array) $this->input('paradas', []) as $indice => $parada) {
                    $mismaZona = ($parada['zona_origen'] ?? null) !== null
                        && ($parada['zona_origen'] ?? null) === ($parada['zona_destino'] ?? null);
                    $lugarOrigen = mb_strtolower(trim((string) ($parada['lugar_origen'] ?? '')));
                    $lugarDestino = mb_strtolower(trim((string) ($parada['lugar_destino'] ?? '')));

                    if ($mismaZona && ($lugarOrigen === '' || $lugarDestino === '' || $lugarOrigen === $lugarDestino)) {
                        $validator->errors()->add("paradas.{$indice}.zona_destino", 'El destino tiene que ser distinto del origen.');
                    }
                }

                $vuelta = $this->vueltaEditada();

                // Dos tickets por salir de la misma unidad: las GR caerían
                // en uno solo y el otro quedaría colgado para siempre.
                $otra = Vuelta::query()
                    ->where('tracto_id', $this->integer('tracto_id'))
                    ->where('estado', EstadoVuelta::Programada)
                    ->when($vuelta, fn ($query) => $query->whereKeyNot($vuelta->id))
                    ->first();

                if ($otra !== null) {
                    $validator->errors()->add('tracto_id', "La unidad ya tiene el ticket {$otra->codigo} por salir el {$otra->inicio?->format('d/m')}: edítalo en vez de programar otro.");
                }

                // Con GR, la salida no puede quedar después de la primera:
                // un día de holgura, como al asignar las GR.
                $primera = $vuelta === null ? null : Viaje::query()
                    ->whereIn('parada_vuelta_id', $vuelta->paradas()->select('id'))
                    ->min('fecha_traslado');

                if ($primera !== null && $this->date('inicio')?->gt(Carbon::parse($primera)->addDay())) {
                    $validator->errors()->add('inicio', 'La primera GR del ticket es del '.Carbon::parse($primera)->format('d/m').': la salida no puede ser después.');
                }
            },
        ];
    }

    private function vueltaEditada(): ?Vuelta
    {
        $vuelta = $this->route('vuelta');

        return $vuelta instanceof Vuelta ? $vuelta : null;
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
            'paradas.*.id' => 'parada',
            'paradas.*.zona_origen' => 'origen',
            'paradas.*.zona_destino' => 'destino',
            'paradas.*.tipo_carga' => 'carga',
            'paradas.*.cliente_id' => 'cliente',
        ];
    }
}
