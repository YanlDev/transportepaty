<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El padrón de clientes. A diferencia de conductores y vehículos, este no se
 * carga a mano de cero: los clientes ya existen en las GR que se importan, y
 * el alta sirve para ponerles alias corto, contacto y saber con quién se
 * trabaja seguido.
 */
class ClienteController extends Controller
{
    /**
     * Cuántos viajes se listan en la ficha del cliente: un vistazo a lo
     * último que se le movió, no el historial completo —ese vive en
     * `/viajes` filtrado por su nombre.
     */
    private const VIAJES_RECIENTES = 10;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cliente::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $clientes = Cliente::query()
            ->withCount('viajes')
            ->withMax('viajes', 'fecha_traslado')
            ->when($filtros['buscar'], fn ($query, string $buscar) => $query->buscar($buscar))
            // Los que más mueven primero: es el orden en que se los busca.
            ->orderByDesc('viajes_count')
            ->orderBy('alias')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Cliente $cliente): array => [
                'id' => $cliente->id,
                'ruc' => $cliente->ruc,
                'razon_social' => $cliente->razon_social,
                'alias' => $cliente->alias,
                'contacto' => $cliente->contacto,
                'telefono' => $cliente->telefono,
                'recurrente' => $cliente->recurrente,
                'activo' => $cliente->activo,
                'viajes_count' => $cliente->viajes_count,
                'ultimo_viaje' => $cliente->viajes_max_fecha_traslado,
            ]);

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
            'filtros' => $filtros,
            // El tope global —no el de la página— para que la barra de
            // proporción signifique lo mismo en la página 1 que en la 2.
            'maxViajes' => $this->maxViajesPorCliente(),
        ]);
    }

    /**
     * Cuántos viajes tiene el cliente que más mueve. Es solo la escala de la
     * barra de proporción del listado.
     *
     * Sale de una agregación y no de traer los clientes con su conteo: eso
     * hidrataba el padrón entero en PHP —una segunda pasada completa, además
     * de la paginada— para quedarse con un número.
     */
    private function maxViajesPorCliente(): int
    {
        $conteos = Viaje::query()
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->selectRaw('count(*) as total');

        return (int) DB::query()->fromSub($conteos, 'conteos')->max('total');
    }

    public function show(Cliente $cliente): Response
    {
        $this->authorize('view', $cliente);

        return Inertia::render('clientes/show', [
            'cliente' => $cliente,
            'estadisticas' => $this->estadisticas($cliente),
            'viajes' => $cliente->viajes()
                // `media` evita el N+1 de `getFirstMediaUrl()` de abajo.
                ->with(['media', 'conductor:id,nombres,apellidos'])
                ->orderByDesc('fecha_traslado')
                ->orderByDesc('numero_gr')
                ->limit(self::VIAJES_RECIENTES)
                ->get()
                ->map(fn (Viaje $viaje): array => [
                    'id' => $viaje->id,
                    'numero_gr' => $viaje->numero_gr,
                    'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                    'placa_tracto' => $viaje->placa_tracto,
                    'placa_carreta' => $viaje->placa_carreta,
                    'conductor_nombre' => $viaje->conductor_nombre,
                    'conductor_id' => $viaje->conductor_id,
                    'origen_ciudad' => $viaje->ciudadOrigen(),
                    'destino_ciudad' => $viaje->ciudadDestino(),
                    'tipo_carga' => $viaje->tipo_carga->value,
                    'tipo_carga_label' => $viaje->tipo_carga->label(),
                    'peso' => (float) $viaje->peso,
                    'unidad_peso' => $viaje->unidad_peso,
                    'archivo_url' => $viaje->getFirstMediaUrl('archivo') ?: null,
                ])
                ->all(),
        ]);
    }

    /**
     * Los números de cabecera de la ficha. La variación contra el mes
     * anterior sí se puede calcular —los viajes tienen fecha— a diferencia de
     * los indicadores de flota del tablero, que necesitarían un histórico
     * que no se guarda.
     *
     * @return array{
     *     viajes_totales: int,
     *     viajes_mes: int,
     *     variacion_mes: float|null,
     *     ultimo_viaje: string|null,
     *     primer_viaje: string|null,
     *     tipos_carga: int,
     *     carga_principal: string|null,
     *     ruta_frecuente: array{origen: string, destino: string, viajes: int}|null,
     * }
     */
    private function estadisticas(Cliente $cliente): array
    {
        $viajes = $cliente->viajes()->get(['fecha_traslado', 'origen', 'destino', 'tipo_carga']);

        $inicioMes = RelojOperativo::inicioDelMes();
        $inicioMesAnterior = $inicioMes->subMonth();

        $delMes = $viajes->filter(
            fn (Viaje $viaje): bool => $viaje->fecha_traslado->gte($inicioMes)
        )->count();

        $delMesAnterior = $viajes->filter(
            fn (Viaje $viaje): bool => $viaje->fecha_traslado->gte($inicioMesAnterior)
                && $viaje->fecha_traslado->lt($inicioMes)
        )->count();

        $porTipo = $viajes->countBy(fn (Viaje $viaje): string => $viaje->tipo_carga->label())->sortDesc();

        $porRuta = $viajes
            ->countBy(fn (Viaje $viaje): string => $viaje->ciudadOrigen().'|'.$viaje->ciudadDestino())
            ->sortDesc();

        // `countBy` deja el valor agrupado como clave del arreglo, y PHP
        // convierte a entero cualquier clave que parezca un número. Se
        // devuelven a texto al leerlas porque son etiquetas y nombres de
        // ciudad, no números.
        $rutaFrecuente = $porRuta->keys()->map(strval(...))->first();
        $cargaPrincipal = $porTipo->keys()->map(strval(...))->first();

        // Las fechas ya en `Y-m-d`, que ordena igual que cronológicamente.
        $fechas = $viajes->map(fn (Viaje $viaje): string => $viaje->fecha_traslado->toDateString());

        return [
            'viajes_totales' => $viajes->count(),
            'viajes_mes' => $delMes,
            // Sin mes anterior con qué comparar no hay variación que mostrar:
            // «+100%» desde cero diría más de lo que se sabe.
            'variacion_mes' => $delMesAnterior > 0
                ? round(($delMes - $delMesAnterior) / $delMesAnterior * 100, 1)
                : null,
            'ultimo_viaje' => $fechas->max(),
            'primer_viaje' => $fechas->min(),
            'tipos_carga' => $porTipo->count(),
            'carga_principal' => $cargaPrincipal,
            'ruta_frecuente' => $rutaFrecuente === null ? null : [
                'origen' => explode('|', $rutaFrecuente)[0],
                'destino' => explode('|', $rutaFrecuente)[1],
                'viajes' => $porRuta->first() ?? 0,
            ],
        ];
    }

    public function create(): Response
    {
        $this->authorize('create', Cliente::class);

        return Inertia::render('clientes/create');
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $this->authorize('create', Cliente::class);

        $cliente = Cliente::create($request->validated());

        // Un cliente recién dado de alta suele tener viajes esperándolo: las
        // GR se importan antes de que alguien lo registre, y quedaron con el
        // RUC pero sin enlazar.
        $enlazados = Viaje::query()
            ->where('cliente_ruc', $cliente->ruc)
            ->whereNull('cliente_id')
            ->update(['cliente_id' => $cliente->id]);

        return to_route('clientes.index')->with('toast', [
            'type' => 'success',
            'message' => $enlazados > 0
                ? "Cliente registrado y {$enlazados} viaje(s) enlazado(s)."
                : 'Cliente registrado correctamente.',
        ]);
    }

    public function edit(Cliente $cliente): Response
    {
        $this->authorize('update', $cliente);

        return Inertia::render('clientes/edit', ['cliente' => $cliente]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $cliente->update($request->validated());

        return to_route('clientes.index', $request->query())->with('toast', [
            'type' => 'success',
            'message' => 'Cliente actualizado correctamente.',
        ]);
    }

    /**
     * Borrar el cliente no borra su historial: los viajes conservan el nombre
     * y el RUC que trajo la GR y solo pierden el enlace al padrón (la FK es
     * `nullOnDelete`).
     */
    public function destroy(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $cliente->delete();

        return to_route('clientes.index', $request->query())->with('toast', [
            'type' => 'success',
            'message' => 'Cliente eliminado. Sus viajes conservan el nombre de la GR.',
        ]);
    }
}
