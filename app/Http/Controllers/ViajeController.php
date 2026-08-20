<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Http\Requests\StoreViajeManualRequest;
use App\Http\Requests\StoreViajeRequest;
use App\Http\Requests\UpdateTipoCargaViajeRequest;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\ImportadorViaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ViajeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Viaje::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $viajes = Viaje::query()
            ->with(['tracto:id,placa', 'carreta:id,placa', 'conductor:id,nombres,apellidos'])
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('placa_tracto', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('placa_carreta', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('cliente', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('destinatario', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('conductor_nombre', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('numero_gr', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('origen', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('destino', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Viaje $viaje): array => [
                'id' => $viaje->id,
                'numero_gr' => $viaje->numero_gr,
                'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                'placa_tracto' => $viaje->placa_tracto,
                'placa_carreta' => $viaje->placa_carreta,
                'tracto_id' => $viaje->tracto_id,
                'carreta_id' => $viaje->carreta_id,
                'conductor_nombre' => $viaje->conductor_nombre,
                'conductor_id' => $viaje->conductor_id,
                'cliente' => $viaje->cliente,
                'destinatario' => $viaje->destinatario,
                'origen' => $viaje->origen,
                'destino' => $viaje->destino,
                'destino_region' => $viaje->regionDestino(),
                'tipo_carga' => $viaje->tipo_carga->value,
                'tipo_carga_label' => $viaje->tipo_carga->label(),
                'peso' => (float) $viaje->peso,
                'unidad_peso' => $viaje->unidad_peso,
                'archivo_url' => $viaje->getFirstMediaUrl('archivo') ?: null,
            ]);

        return Inertia::render('viajes/index', [
            'viajes' => $viajes,
            'filtros' => $filtros,
            'pendientes' => $this->pendientes()->count(),
            'tiposCarga' => TipoCarga::opcionesDeViaje(),
        ]);
    }

    /**
     * Sube uno o varios PDFs de GR y crea/actualiza un `Viaje` por cada uno.
     * Los que no se reconocen (no son una GRE de Paty, o vienen incompletos)
     * se saltan sin tumbar el resto del lote.
     */
    public function store(StoreViajeRequest $request, ImportadorViaje $importador): RedirectResponse
    {
        $this->authorize('create', Viaje::class);

        $creados = 0;
        $noReconocidos = 0;

        foreach ($request->file('archivos') as $archivo) {
            $resultado = $importador->importar($archivo);

            if ($resultado['reconocido']) {
                $creados++;
            } else {
                $noReconocidos++;
            }
        }

        $mensaje = $creados === 1 ? '1 viaje importado.' : "{$creados} viajes importados.";

        if ($noReconocidos > 0) {
            $mensaje .= $noReconocidos === 1
                ? ' 1 archivo no se reconoció como GR de Paty.'
                : " {$noReconocidos} archivos no se reconocieron como GR de Paty.";
        }

        return back()->with('toast', [
            'type' => $creados > 0 ? 'success' : 'info',
            'message' => $mensaje,
        ]);
    }

    /**
     * Formulario para un viaje que nunca va a tener GR-transportista: cuando
     * la GRE-Remitente ya trae vehículo y conductor, SUNAT no deja emitir una
     * GRE-Transportista aparte, así que no hay PDF que importar. Sin esto el
     * viaje simplemente no quedaría registrado.
     */
    public function create(): Response
    {
        $this->authorize('create', Viaje::class);

        return Inertia::render('viajes/create', $this->opcionesFormulario());
    }

    public function storeManual(StoreViajeManualRequest $request): RedirectResponse
    {
        $this->authorize('create', Viaje::class);

        $tracto = Vehiculo::query()->findOrFail($request->integer('tracto_id'));
        $carreta = $request->filled('carreta_id')
            ? Vehiculo::query()->find($request->integer('carreta_id'))
            : null;
        $conductor = Conductor::query()->findOrFail($request->integer('conductor_id'));

        // En mayúsculas: es el mismo tono en el que llega todo lo que se lee
        // de una GR-transportista real, y estos campos conviven con esos en
        // la misma tabla.
        $viaje = Viaje::create([
            'numero_gr' => Str::upper($request->string('numero_gr')->value()),
            'fecha_emision' => $request->string('fecha_traslado')->value(),
            'fecha_traslado' => $request->string('fecha_traslado')->value(),
            'origen' => Str::upper($request->string('origen')->value()),
            'destino' => Str::upper($request->string('direccion_destino')->value().' - '.$request->string('departamento_destino')->value()),
            'cliente' => Str::upper($request->string('cliente')->value()),
            'destinatario' => Str::upper($request->string('destinatario')->value()),
            'peso' => $request->float('peso'),
            'unidad_peso' => Str::upper($request->string('unidad_peso')->value()),
            'placa_tracto' => $tracto->placa,
            'placa_carreta' => $carreta?->placa,
            'tracto_id' => $tracto->id,
            'carreta_id' => $carreta?->id,
            // En mayúsculas y apellidos primero: así llega el nombre del
            // chofer en toda GR-transportista real, y conductor_nombre existe
            // para mostrarse junto al resto de viajes sin desentonar.
            'conductor_nombre' => Str::upper("{$conductor->apellidos} {$conductor->nombres}"),
            'conductor_dni' => $conductor->documento,
            'conductor_id' => $conductor->id,
            'tipo_carga' => $request->string('tipo_carga')->value(),
            'observaciones' => $request->string('observaciones')->value() ?: null,
        ]);

        if ($request->hasFile('archivo')) {
            $viaje->addMediaFromRequest('archivo')->toMediaCollection('archivo');
        }

        return to_route('viajes.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Viaje registrado manualmente.',
            ]);
    }

    /**
     * El tipo de carga no viene en la GR —solo lo sabe quien clasifica el
     * archivo en su carpeta local—, así que se corrige a mano desde la tabla.
     */
    public function actualizarTipoCarga(UpdateTipoCargaViajeRequest $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('update', $viaje);

        $viaje->update(['tipo_carga' => $request->validated('tipo_carga')]);

        return back();
    }

    public function destroy(Viaje $viaje): RedirectResponse
    {
        $this->authorize('delete', $viaje);

        $viaje->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Viaje eliminado.',
        ]);
    }

    /**
     * Vuelve a intentar resolver tracto, carreta y conductor de los viajes que
     * quedaron sin coincidencia, contra el padrón de hoy —típicamente porque
     * la GR se subió antes de cargar el vehículo o el conductor.
     */
    public function resolver(ImportadorViaje $importador): RedirectResponse
    {
        $this->authorize('create', Viaje::class);

        $resueltos = 0;

        foreach ($this->pendientes()->get() as $viaje) {
            if ($importador->reintentar($viaje)) {
                $resueltos++;
            }
        }

        $mensaje = match (true) {
            $resueltos === 0 => 'No se encontraron coincidencias nuevas.',
            $resueltos === 1 => '1 viaje actualizado.',
            default => "{$resueltos} viajes actualizados.",
        };

        return back()->with('toast', [
            'type' => $resueltos > 0 ? 'success' : 'info',
            'message' => $mensaje,
        ]);
    }

    /**
     * Opciones del formulario manual: toda la flota que todavía no salió de
     * la operación y los conductores activos. A diferencia de Asignaciones,
     * no se filtra por «libre»: un viaje puede registrarse para una unidad
     * que ya está armada, no solo para las que están paradas.
     *
     * @return array<string, mixed>
     */
    private function opcionesFormulario(): array
    {
        return [
            'tractos' => $this->opcionesDeVehiculo(TipoVehiculo::Tracto),
            'carretas' => $this->opcionesDeVehiculo(TipoVehiculo::Carreta),
            'conductores' => Conductor::query()
                ->where('activo', true)
                ->orderBy('nombres')
                ->orderBy('apellidos')
                ->get(['id', 'nombres', 'apellidos', 'telefono'])
                ->map(fn (Conductor $conductor): array => [
                    'id' => $conductor->id,
                    'nombre_completo' => $conductor->nombre_completo,
                    'telefono' => $conductor->telefono,
                ]),
            'tiposCarga' => TipoCarga::opcionesDeViaje(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, placa: string, descripcion: string}>
     */
    private function opcionesDeVehiculo(TipoVehiculo $tipo): Collection
    {
        return Vehiculo::query()
            ->where('tipo', $tipo)
            ->whereIn('estado', EstadoVehiculo::asignables())
            ->orderBy('placa')
            ->get(['id', 'placa', 'marca', 'modelo'])
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'placa' => $vehiculo->placa,
                'descripcion' => $vehiculo->descripcion(),
            ]);
    }

    /**
     * Viajes con al menos un dato sin resolver contra el padrón: sin tracto,
     * sin conductor, o con carreta en el texto pero sin matchear.
     *
     * @return Builder<Viaje>
     */
    private function pendientes(): Builder
    {
        return Viaje::query()->where(function ($query): void {
            $query->whereNull('tracto_id')
                ->orWhereNull('conductor_id')
                ->orWhere(function ($query): void {
                    $query->whereNotNull('placa_carreta')->whereNull('carreta_id');
                });
        });
    }
}
