<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Enums\TipoVehiculo;
use App\Models\Abastecimiento;
use App\Models\RutaVuelta;
use App\Models\Vuelta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El combustible que abastecimiento autoriza (el QR de Repsol, o Kio). Nada
 * se deduce: el ticket y el tramo los elige quien registra, el ticket tiene
 * que ser de esa unidad y estar listo (OK de taller, ruta con tope), y lo
 * que pase del tope de sus rutas necesita motivo y permiso.
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
            // Con qué precio se autoriza: el costo del ticket se ve desde ya.
            'precio_galon' => ['required', 'numeric', 'min:0.01', 'max:99'],
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
                $rutas = $vuelta->rutasDe($sentido);

                if ($rutas->isEmpty()) {
                    $validator->errors()->add('sentido', "La {$sentido->label()} del ticket {$vuelta->codigo} no tiene ruta: se elige al programarlo.");

                    return;
                }

                if ($vuelta->topeDe($sentido) === null) {
                    $sinVariante = $rutas->first(fn (RutaVuelta $ruta): bool => $ruta->variante_pendiente);
                    $validator->errors()->add('sentido', $sinVariante !== null
                        ? "Falta elegir la variante de «{$sinVariante->nombre}» (según el peso) para saber el tope."
                        : 'Una ruta del tramo tiene galones por definir: complétala en el catálogo de rutas.');

                    return;
                }

                if (($error = $this->errorDeTope($vuelta)) !== null) {
                    $validator->errors()->add(...$error);
                }
            },
        ];
    }

    /**
     * Si lo que se pide pasa el tope del tramo sin el permiso o sin el
     * motivo: el campo y el mensaje, o null si está bien. Se vuelve a mirar
     * al grabar, con el ticket bloqueado, para que dos registros a la vez no
     * pasen el tope entre los dos.
     *
     * @return array{0: string, 1: string}|null
     */
    public function errorDeTope(Vuelta $vuelta): ?array
    {
        $sentido = SentidoTramo::from($this->string('sentido')->toString());
        $tope = $vuelta->topeDe($sentido);

        if ($tope === null) {
            return null;
        }

        $autorizado = (float) Abastecimiento::query()
            ->vigentes()
            ->where('vuelta_id', $vuelta->id)
            ->where('sentido', $sentido)
            ->when($this->registroQueSeCorrige(), fn ($query, int $id) => $query->whereKeyNot($id))
            ->sum(DB::raw(Abastecimiento::GALONES_QUE_CUENTAN));

        if ($autorizado + $this->galonesQueSeSuman() <= $tope + 0.001) {
            return null;
        }

        $disponible = max(0.0, $tope - $autorizado);
        $resumen = 'Pasa el tope de las rutas ('.$this->galones($tope).' gal; quedan '.$this->galones($disponible).')';

        if (! $this->user()?->can('excederTope', Abastecimiento::class)) {
            return ['galones', "{$resumen}: solo quien tiene permiso puede autorizar más."];
        }

        return blank($this->input('motivo_exceso')) ? ['motivo_exceso', "{$resumen}: escribe por qué."] : null;
    }

    /**
     * Lo que se graba del combustible, igual al registrarlo que al corregirlo.
     *
     * @return array{sentido: SentidoTramo, punto_abastecimiento_id: int|null, punto_libre: string|null, fecha: Carbon, galones: float, precio_autorizado: float, motivo_exceso: string|null, observacion: string|null}
     */
    public function datosDelCombustible(): array
    {
        return [
            'sentido' => $this->enum('sentido', SentidoTramo::class),
            'punto_abastecimiento_id' => $this->filled('punto_abastecimiento_id') ? $this->integer('punto_abastecimiento_id') : null,
            'punto_libre' => $this->filled('punto_libre') ? $this->string('punto_libre')->squish()->toString() : null,
            'fecha' => $this->date('fecha'),
            'galones' => $this->float('galones'),
            'precio_autorizado' => $this->float('precio_galon'),
            'motivo_exceso' => $this->filled('motivo_exceso') ? $this->string('motivo_exceso')->squish()->toString() : null,
            'observacion' => $this->string('observacion')->toString() ?: null,
        ];
    }

    /**
     * Lo que este registro suma al tramo: al registrarlo, lo que se autoriza.
     */
    protected function galonesQueSeSuman(): float
    {
        return $this->float('galones');
    }

    /**
     * El registro que se está corrigiendo, para que no cuente contra su
     * propio tope. Al registrar uno nuevo no hay ninguno.
     */
    protected function registroQueSeCorrige(): ?int
    {
        return null;
    }

    public function vuelta(): Vuelta
    {
        return Vuelta::query()->with(['tracto:id,placa', 'rutas', 'revision'])->findOrFail($this->integer('vuelta_id'));
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
            'precio_galon' => 'precio por galón',
            'motivo_exceso' => 'motivo',
        ];
    }
}
