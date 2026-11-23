<?php

namespace App\Http\Controllers;

use App\Enums\CondicionLlanta;
use App\Enums\TipoMovimientoLlanta;
use App\Enums\TipoVehiculo;
use App\Http\Requests\CodificarLlantasRequest;
use App\Models\DetalleIngreso;
use App\Models\Llanta;
use App\Models\MovimientoLlanta;
use App\Models\ParametroMantenimiento;
use App\Services\LlantasDeAlmacen;
use App\Services\ProximoMantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las llantas como piezas: los lotes en que entraron al almacén, el código
 * de cada una, y la ficha con dónde está y todo lo que se le hizo.
 */
class LlantaAlmacenController extends Controller
{
    public function __construct(private readonly LlantasDeAlmacen $deAlmacen) {}

    /**
     * Los lotes (cada recepción con llantas) y sus llantas.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Llanta::class);

        return Inertia::render('mantenimiento/llantas/almacen', [
            'lotes' => $this->deAlmacen->lotes(),
            'condiciones' => CondicionLlanta::options(),
            'puedeRegistrar' => $request->user()?->can('create', Llanta::class) ?? false,
        ]);
    }

    /**
     * Ponerles código a las llantas de una línea de recepción.
     */
    public function store(CodificarLlantasRequest $request): RedirectResponse
    {
        $linea = DetalleIngreso::query()->findOrFail($request->integer('detalle_ingreso_id'));
        $codigos = array_values($request->codigos());

        DB::transaction(fn () => $this->deAlmacen->codificar(
            $linea,
            $codigos,
            CondicionLlanta::from($request->string('condicion')->value()),
            $request->filled('medida') ? $request->string('medida')->value() : null,
        ));

        $cantidad = count($codigos);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $cantidad === 1 ? "Llanta {$codigos[0]} registrada: ya se puede montar." : "{$cantidad} llantas registradas: ya se pueden montar.",
        ]);
    }

    /**
     * Quitar una llanta que se codificó por error. Solo la que nunca salió
     * del almacén: no tiene vale ni movimientos.
     */
    public function destroy(Llanta $llanta): RedirectResponse
    {
        $this->authorize('update', Llanta::class);

        if (! $llanta->estaSinMontar() || $llanta->detalle_salida_id !== null || $llanta->movimientos()->exists()) {
            throw ValidationException::withMessages(['llanta' => "La llanta {$llanta->codigo} ya salió del almacén: no se puede quitar."]);
        }

        $llanta->delete();

        return back()->with('toast', ['type' => 'success', 'message' => "Llanta {$llanta->codigo} quitada: vuelve a estar por codificar."]);
    }

    /**
     * Buscar una llanta por su código. Si es una sola, va directo a su ficha.
     */
    public function buscar(Request $request): Response|RedirectResponse
    {
        $this->authorize('viewAny', Llanta::class);

        $codigo = trim($request->string('codigo')->value());
        $encontradas = $codigo === ''
            ? collect()
            : Llanta::query()
                ->whereRaw('lower(codigo) like ?', ['%'.mb_strtolower($codigo).'%'])
                ->with(['vehiculo:id,placa', 'detalleSalida.salida.vehiculo:id,placa'])
                ->orderBy('codigo')
                ->limit(50)
                ->get();

        $exacta = $encontradas->first(fn (Llanta $llanta): bool => mb_strtolower((string) $llanta->codigo) === mb_strtolower($codigo));

        if ($exacta !== null || $encontradas->count() === 1) {
            return to_route('mantenimiento.llantas.ficha', $exacta ?? $encontradas->first());
        }

        return Inertia::render('mantenimiento/llantas/buscar', [
            'codigo' => $codigo,
            'llantas' => $encontradas->map(fn (Llanta $llanta): array => [
                'id' => $llanta->id,
                'codigo' => $llanta->codigo,
                'descripcion' => trim(implode(' ', array_filter([$llanta->marca, $llanta->medida]))),
                ...$this->deAlmacen->ubicacion($llanta),
            ])->values()->all(),
        ]);
    }

    /**
     * La ficha de la llanta: dónde está, cuánto rodó y todo lo que se le hizo.
     */
    public function ficha(Llanta $llanta, ProximoMantenimiento $mantenimiento): Response
    {
        $this->authorize('viewAny', Llanta::class);

        $llanta->load(['vehiculo', 'articulo:id,nombre,codigo', 'detalleIngreso.ingreso.proveedor', 'detalleSalida.salida.vehiculo:id,placa']);

        $movimientos = MovimientoLlanta::query()
            ->where('llanta_id', $llanta->id)
            ->with(['vehiculo:id,placa', 'user:id,name'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $kmActual = $llanta->estaMontada() && $llanta->vehiculo?->tipo === TipoVehiculo::Tracto
            ? $mantenimiento->paraVehiculo($llanta->vehiculo)['km_actual']
            : null;
        $recorrido = $this->recorrido($movimientos->all(), $kmActual);
        $ultimaMedida = $movimientos->last(fn (MovimientoLlanta $movimiento): bool => $movimiento->profundidad_mm !== null);
        $profundidad = $ultimaMedida === null ? null : (float) $ultimaMedida->profundidad_mm;
        $ingreso = $llanta->detalleIngreso?->ingreso;
        $costo = $llanta->costo === null ? null : (float) $llanta->costo;

        return Inertia::render('mantenimiento/llantas/ficha', [
            'llanta' => [
                'id' => $llanta->id,
                'codigo' => $llanta->codigo,
                'marca' => $llanta->marca,
                'medida' => $llanta->medida,
                'articulo' => $llanta->articulo?->nombre,
                'condicion' => $llanta->condicion->label(),
                'origen' => $llanta->origen->label(),
                ...$this->deAlmacen->ubicacion($llanta),
                'vehiculo_id' => $llanta->estaMontada() ? $llanta->vehiculo_id : null,
                'instalada_el' => $llanta->estaMontada() ? $llanta->instalada_el?->toDateString() : null,
                'km_recorridos' => $recorrido['km'],
                'km_incompleto' => $recorrido['incompleto'],
                'profundidad_mm' => $profundidad,
                'medida_el' => $ultimaMedida?->fecha->toDateString(),
                'estado' => $llanta->estaMontada() ? ParametroMantenimiento::vigentes()->estadoLlanta($profundidad)->value : null,
                'costo' => $costo,
                'costo_por_km' => $costo !== null && $recorrido['km'] > 0 ? round($costo / $recorrido['km'], 4) : null,
                'lote' => $ingreso === null ? null : [
                    'numero' => $ingreso->numero(),
                    'fecha' => $ingreso->fecha->toDateString(),
                    'proveedor' => $ingreso->proveedor?->nombre(),
                ],
                'vale' => $llanta->detalleSalida?->salida->numero(),
            ],
            'movimientos' => $movimientos->reverse()->values()->map(fn (MovimientoLlanta $movimiento): array => [
                'id' => $movimiento->id,
                'tipo' => $movimiento->tipo->value,
                'tipo_label' => $movimiento->tipo->label(),
                'fecha' => $movimiento->fecha->toDateString(),
                'placa' => $movimiento->vehiculo?->placa,
                'vehiculo_id' => $movimiento->vehiculo_id,
                'posicion' => $movimiento->posicion,
                'posicion_destino' => $movimiento->posicion_destino,
                'kilometraje' => $movimiento->kilometraje,
                'profundidad_mm' => $movimiento->profundidad_mm === null ? null : (float) $movimiento->profundidad_mm,
                'observacion' => $movimiento->observacion,
                'usuario' => $movimiento->user?->name,
            ])->all(),
        ]);
    }

    /**
     * Los kilómetros que rodó: de cada montaje, del km en que se instaló al
     * km en que se retiró (o al de hoy, si sigue puesta). Un tramo sin alguno
     * de los dos no se puede contar, y se avisa.
     *
     * @param  array<int, MovimientoLlanta>  $movimientos  del más antiguo al más reciente
     * @return array{km: int, incompleto: bool}
     */
    private function recorrido(array $movimientos, ?int $kmActual): array
    {
        $km = 0;
        $incompleto = false;
        $abierto = false;
        $desde = null;

        foreach ($movimientos as $movimiento) {
            if ($movimiento->tipo === TipoMovimientoLlanta::Instalacion) {
                $abierto = true;
                $desde = $movimiento->kilometraje;
            } elseif ($movimiento->tipo === TipoMovimientoLlanta::Retiro && $abierto) {
                if ($desde !== null && $movimiento->kilometraje !== null && $movimiento->kilometraje >= $desde) {
                    $km += $movimiento->kilometraje - $desde;
                } else {
                    $incompleto = true;
                }

                $abierto = false;
            }
        }

        if ($abierto) {
            if ($desde !== null && $kmActual !== null && $kmActual >= $desde) {
                $km += $kmActual - $desde;
            } else {
                $incompleto = true;
            }
        }

        return ['km' => $km, 'incompleto' => $incompleto];
    }
}
