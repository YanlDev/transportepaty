<?php

namespace App\Http\Requests;

use App\Enums\EstadoVehiculo;
use App\Enums\EstadoVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Enums\ZonaRuta;
use App\Models\Conductor;
use App\Models\Ruta;
use App\Models\RutaVuelta;
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
            'paradas.*.viaje_continuado_id' => ['nullable', Rule::exists('viajes', 'id')],
            // El lugar exacto cuando la zona no alcanza: «Tacna», «Ilo».
            'paradas.*.lugar_origen' => ['nullable', 'string', 'max:100'],
            'paradas.*.lugar_destino' => ['nullable', 'string', 'max:100'],
            // Las rutas de cada tramo: la principal y sus complementos, con
            // la variante si ya se sabe. De ahí sale lo autorizado.
            'rutas' => ['array', 'max:10'],
            'rutas.*.sentido' => ['required', Rule::enum(SentidoTramo::class)],
            'rutas.*.ruta_id' => ['required', 'integer', Rule::exists('rutas', 'id')],
            'rutas.*.variante_ruta_id' => ['nullable', 'integer', Rule::exists('variantes_ruta', 'id')],
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

                    // La carga que sigue tiene que ser de esta unidad: la GR
                    // de otra placa no sube en este carro.
                    $continuada = filled($parada['viaje_continuado_id'] ?? null)
                        ? Viaje::query()->find((int) $parada['viaje_continuado_id'], ['id', 'tracto_id', 'numero_gr'])
                        : null;

                    if ($continuada !== null && $continuada->tracto_id !== $this->integer('tracto_id')) {
                        $validator->errors()->add("paradas.{$indice}.viaje_continuado_id", "La GR {$continuada->numero_gr} es de otra unidad: no sigue en este ticket.");
                    }
                }

                $vuelta = $this->vueltaEditada();

                // El OK de taller y los gastos son de la unidad que se
                // revisó: cambiarle la placa los dejaría en la equivocada.
                if ($vuelta !== null && $vuelta->tracto_id !== $this->integer('tracto_id') && $vuelta->tieneRevisionOGastos()) {
                    $validator->errors()->add('tracto_id', "El ticket {$vuelta->codigo} ya pasó por taller o tiene gastos de {$vuelta->tracto->placa}: no se le cambia la unidad. Si se programó con la placa equivocada y no tiene gastos, quítalo y prográmalo de nuevo.");
                }

                $this->validarRutas($validator, $vuelta);

                if ($vuelta !== null) {
                    $this->validarRutasCongeladas($validator, $vuelta);
                }

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

                // El siguiente se programa cuando la unidad volvió: mientras
                // anda, su ticket sigue abierto y nada lo cierra solo.
                $enRuta = $vuelta !== null ? null : Vuelta::query()
                    ->where('tracto_id', $this->integer('tracto_id'))
                    ->where('estado', EstadoVuelta::EnCurso)
                    ->whereNull('llegada_el')
                    ->first();

                if ($enRuta !== null) {
                    $validator->errors()->add('tracto_id', "La unidad sigue en ruta con el ticket {$enRuta->codigo}: registra su llegada a la base antes de programar el siguiente.");
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

    /**
     * Cada tramo con paradas lleva una sola ruta principal (y los
     * complementos que hagan falta); cada ruta sirve para su tramo y la
     * variante es de esa ruta. Una ruta ya desactivada se puede dejar en el
     * ticket que la tenía, pero no elegir de nuevo.
     */
    private function validarRutas(Validator $validator, ?Vuelta $vuelta): void
    {
        if ($validator->errors()->hasAny(['rutas', 'rutas.*'])) {
            return;
        }

        $elegidas = collect((array) $this->input('rutas', []));
        $catalogo = Ruta::query()->with('variantes')->findMany($elegidas->pluck('ruta_id'))->keyBy('id');
        $yaTenia = $vuelta?->rutas()->pluck('ruta_id')->all() ?? [];

        foreach ($elegidas as $indice => $elegida) {
            $ruta = $catalogo->get((int) $elegida['ruta_id']);
            $sentido = SentidoTramo::from($elegida['sentido']);

            if ($ruta === null) {
                continue;
            }

            if (! $ruta->sirvePara($sentido)) {
                $validator->errors()->add("rutas.{$indice}.ruta_id", "«{$ruta->nombre}» no es una ruta de {$sentido->label()}.");
            } elseif (! $ruta->activa && ! in_array($ruta->id, $yaTenia, true)) {
                $validator->errors()->add("rutas.{$indice}.ruta_id", "«{$ruta->nombre}» está desactivada.");
            }

            if (filled($elegida['variante_ruta_id'] ?? null) && ! $ruta->variantes->contains('id', (int) $elegida['variante_ruta_id'])) {
                $validator->errors()->add("rutas.{$indice}.variante_ruta_id", "Esa variante no es de «{$ruta->nombre}».");
            }
        }

        foreach (SentidoTramo::cases() as $sentido) {
            $conParadas = collect((array) $this->input('paradas', []))->contains(fn (array $parada): bool => ($parada['sentido'] ?? null) === $sentido->value);
            $principales = $elegidas
                ->filter(fn (array $elegida): bool => $elegida['sentido'] === $sentido->value)
                ->filter(fn (array $elegida): bool => ! ($catalogo->get((int) $elegida['ruta_id'])->es_complemento ?? true))
                ->count();

            if ($conParadas && $principales === 0) {
                $validator->errors()->add('rutas', "Elige la ruta de la {$sentido->label()}: de ahí sale lo que se le autoriza.");
            } elseif ($principales > 1) {
                $validator->errors()->add('rutas', "La {$sentido->label()} tiene más de una ruta principal: deja una y agrega lo demás como complemento.");
            }
        }
    }

    /**
     * Un tramo al que ya se le autorizó combustible o se le depositó un
     * viático no cambia de rutas: lo autorizado se midió contra ellas. Solo
     * se puede completar la variante que faltaba y agregar complementos (el
     * destino lejano que se supo después, como Huaral: suma, no cambia lo
     * que ya se autorizó).
     */
    private function validarRutasCongeladas(Validator $validator, Vuelta $vuelta): void
    {
        if ($validator->errors()->hasAny(['rutas', 'rutas.*'])) {
            return;
        }

        $elegidas = collect((array) $this->input('rutas', []));

        foreach (SentidoTramo::cases() as $sentido) {
            if (! $vuelta->tramoConGastos($sentido)) {
                continue;
            }

            $tenia = $vuelta->rutas()->where('sentido', $sentido)->get();
            $nuevas = $elegidas->where('sentido', $sentido->value)->values();
            $usadas = [];

            // Cada ruta que tenía sigue, con la misma variante (o la que
            // faltaba elegir).
            $conserva = $tenia->every(function (RutaVuelta $ruta) use ($nuevas, &$usadas): bool {
                $indice = $nuevas->search(fn (array $elegida, int $indice): bool => ! in_array($indice, $usadas, true)
                    && $ruta->ruta_id === (int) $elegida['ruta_id']
                    && (blank($elegida['variante_ruta_id'] ?? null)
                        || $ruta->variante_ruta_id === null
                        || $ruta->variante_ruta_id === (int) $elegida['variante_ruta_id']));

                if ($indice === false) {
                    return false;
                }

                $usadas[] = $indice;

                return true;
            });

            // Lo que se suma solo pueden ser complementos.
            $agregadas = $nuevas->reject(fn (array $elegida, int $indice): bool => in_array($indice, $usadas, true));
            $soloComplementos = $agregadas->isEmpty()
                || Ruta::query()->whereKey($agregadas->pluck('ruta_id')->map(fn ($id): int => (int) $id)->all())->where('es_complemento', false)->doesntExist();

            if (! $conserva || ! $soloComplementos) {
                $validator->errors()->add('rutas', "La {$sentido->label()} ya tiene combustible o viáticos autorizados con sus rutas: no se cambian ni se quitan, solo se pueden sumar complementos. Anula esos gastos antes de cambiarlas.");
            }
        }
    }

    private function vueltaEditada(): ?Vuelta
    {
        $vuelta = $this->route('vuelta');

        return $vuelta instanceof Vuelta ? $vuelta : null;
    }

    /**
     * Lo validado, con cada campo en su tipo.
     *
     * @return array{tracto_id: int, carreta_id: int|null, conductor_id: int|null, inicio: string, paradas: list<array<string, mixed>>, rutas: list<array{sentido: string, ruta_id: int, variante_ruta_id: int|null}>}
     */
    public function datos(): array
    {
        return [
            'rutas' => array_values(array_map(fn (array $ruta): array => [
                'sentido' => $ruta['sentido'],
                'ruta_id' => (int) $ruta['ruta_id'],
                'variante_ruta_id' => filled($ruta['variante_ruta_id'] ?? null) ? (int) $ruta['variante_ruta_id'] : null,
            ], (array) $this->validated('rutas', []))),
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
