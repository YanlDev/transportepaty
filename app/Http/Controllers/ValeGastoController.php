<?php

namespace App\Http\Controllers;

use App\Enums\AreaGasto;
use App\Enums\EstadoValeCompra;
use App\Http\Requests\Almacen\AnularDocumentoRequest;
use App\Http\Requests\ValeGastoRequest;
use App\Models\CategoriaGasto;
use App\Models\User;
use App\Models\ValeGasto;
use App\Models\Vehiculo;
use App\Policies\ValeGastoPolicy;
use App\Services\AlertasDeGastos;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los vales de gasto: cualquier área pide plata para algo que no pasa por el
 * almacén, diciendo en qué categoría cae y para qué es; gerencia lo aprueba
 * o lo rechaza. Quien pide ve los suyos; gerencia y contabilidad, todos.
 */
class ValeGastoController extends Controller
{
    public function __construct(
        private readonly FileUploadService $subidas,
        private readonly AlertasDeGastos $alertas,
        private readonly ValeGastoPolicy $policy,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ValeGasto::class);

        /** @var User $usuario */
        $usuario = $request->user();
        $veTodos = $this->policy->veTodos($usuario);

        $estado = EstadoValeCompra::tryFrom($request->string('estado')->value());
        $area = AreaGasto::tryFrom($request->string('area')->value());
        $categoria = $request->integer('categoria') ?: null;

        $propios = fn ($query) => $query->when(! $veTodos, fn ($query) => $query->where('solicitado_por', $usuario->id));

        $vales = ValeGasto::query()
            ->with(['categoria:id,nombre', 'solicitante:id,name', 'vehiculo:id,placa'])
            ->tap($propios)
            ->when($estado !== null, fn ($query) => $query->vigentes()->where('estado', $estado))
            ->when($area !== null, fn ($query) => $query->where('area', $area))
            ->when($categoria !== null, fn ($query) => $query->where('categoria_gasto_id', $categoria))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ValeGasto $vale): array => $this->cabecera($vale));

        $puedeCategorias = $usuario->can('administrar', CategoriaGasto::class);

        return Inertia::render('gastos/index', [
            'vales' => $vales,
            'filtros' => ['estado' => $estado?->value, 'area' => $area?->value, 'categoria' => $categoria],
            'estados' => EstadoValeCompra::options(),
            'areas' => AreaGasto::options(),
            'categorias' => CategoriaGasto::query()->activas()->get(['id', 'nombre']),
            // Con las desactivadas y cuántos vales tiene cada una, para quien las administra.
            'todasLasCategorias' => $puedeCategorias
                ? CategoriaGasto::query()->withCount('vales')->orderByDesc('activa')->orderBy('nombre')->get()
                    ->map(fn (CategoriaGasto $categoria): array => [
                        'id' => $categoria->id,
                        'nombre' => $categoria->nombre,
                        'activa' => $categoria->activa,
                        'vales' => $categoria->vales_count,
                    ])->all()
                : null,
            'vehiculos' => Vehiculo::query()->orderBy('placa')->get(['id', 'placa'])
                ->map(fn (Vehiculo $vehiculo): array => ['id' => $vehiculo->id, 'placa' => $vehiculo->placa])->all(),
            'areaDelUsuario' => AreaGasto::deUsuario($usuario)->value,
            'porAprobar' => ValeGasto::query()->vigentes()->tap($propios)->where('estado', EstadoValeCompra::Solicitado)->count(),
            'veTodos' => $veTodos,
            'puedeSolicitar' => $usuario->can('create', ValeGasto::class),
            'maximoKb' => (int) config('uploads.imagenes.max_kb'),
        ]);
    }

    public function store(ValeGastoRequest $request): RedirectResponse
    {
        $this->authorize('create', ValeGasto::class);

        /** @var User $usuario */
        $usuario = $request->user();
        $datos = $request->validated();

        $vale = DB::transaction(function () use ($request, $usuario, $datos): ValeGasto {
            $vale = ValeGasto::create([
                'estado' => EstadoValeCompra::Solicitado,
                'area' => $datos['area'] ?? AreaGasto::deUsuario($usuario)->value,
                'categoria_gasto_id' => $datos['categoria_gasto_id'],
                'concepto' => $datos['concepto'],
                'motivo' => $datos['motivo'],
                'monto' => $datos['monto'],
                'beneficiario' => $datos['beneficiario'],
                'vehiculo_id' => $datos['vehiculo_id'] ?? null,
                'solicitado_por' => $usuario->id,
            ]);

            foreach ($request->file('sustento', []) as $indice => $archivo) {
                $this->subidas->guardar($vale, $archivo, 'sustento', "sustento.{$indice}");
            }

            return $vale;
        });

        $this->alertas->porAprobar($vale, $usuario);

        return to_route('gastos.show', $vale)
            ->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} pedido: queda por aprobar."]);
    }

    public function show(Request $request, ValeGasto $vale): Response
    {
        $this->authorize('view', $vale);

        $vale->load(['categoria:id,nombre', 'solicitante:id,name', 'aprobador:id,name', 'rechazadoPor:id,name', 'anuladoPor:id,name', 'vehiculo:id,placa', 'media']);

        $usuario = $request->user();

        return Inertia::render('gastos/show', [
            'vale' => [
                ...$this->cabecera($vale),
                'motivo' => $vale->motivo,
                'aprobado_por' => $vale->aprobador?->name,
                'aprobado_at' => $vale->aprobado_at?->toIso8601String(),
                'rechazado_por' => $vale->rechazadoPor?->name,
                'rechazado_at' => $vale->rechazado_at?->toIso8601String(),
                'motivo_rechazo' => $vale->motivo_rechazo,
                'anulado_por' => $vale->anuladoPor?->name,
                'anulado_at' => $vale->anulado_at?->toIso8601String(),
                'motivo_anulacion' => $vale->motivo_anulacion,
                'sustento' => $vale->getMedia('sustento')->map(fn (Media $archivo): array => [
                    'id' => $archivo->id,
                    'nombre' => $archivo->name,
                    'url' => route('gastos.archivo', [$vale, $archivo]),
                ])->values()->all(),
            ],
            'puede' => [
                'aprobar' => $usuario?->can('aprobar', $vale) ?? false,
                'anular' => $usuario?->can('anular', $vale) ?? false,
            ],
            'esPropio' => $usuario !== null && $vale->solicitado_por === $usuario->id,
        ]);
    }

    /**
     * Nadie aprueba lo que él mismo pidió.
     */
    public function aprobar(Request $request, ValeGasto $vale): RedirectResponse
    {
        $this->authorize('aprobar', $vale);

        if ($vale->solicitado_por === $request->user()?->id) {
            throw ValidationException::withMessages(['aprobacion' => 'No puedes aprobar un vale que pediste tú: lo aprueba otra persona.']);
        }

        $vale->update([
            'estado' => EstadoValeCompra::Aprobado,
            'aprobado_por' => $request->user()?->id,
            'aprobado_at' => now(),
        ]);

        $this->alertas->resuelto($vale, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} aprobado: ya se puede hacer el gasto."]);
    }

    public function rechazar(AnularDocumentoRequest $request, ValeGasto $vale): RedirectResponse
    {
        $this->authorize('aprobar', $vale);

        $vale->update([
            'estado' => EstadoValeCompra::Rechazado,
            'rechazado_por' => $request->user()?->id,
            'rechazado_at' => now(),
            'motivo_rechazo' => $request->validated('motivo'),
        ]);

        $this->alertas->resuelto($vale, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} rechazado."]);
    }

    public function anular(AnularDocumentoRequest $request, ValeGasto $vale): RedirectResponse
    {
        $this->authorize('anular', $vale);

        $vale->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()?->id,
            'motivo_anulacion' => $request->validated('motivo'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "{$vale->numero()} anulado."]);
    }

    /**
     * Un archivo de sustento del vale. Está en el disco privado: solo se
     * abre por acá.
     */
    public function archivo(Request $request, ValeGasto $vale, Media $archivo): StreamedResponse
    {
        $this->authorize('view', $vale);

        abort_unless($archivo->model_type === $vale->getMorphClass() && (int) $archivo->model_id === $vale->id, 404);

        return $archivo->toInlineResponse($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(ValeGasto $vale): array
    {
        return [
            'id' => $vale->id,
            'numero' => $vale->numero(),
            'estado' => $vale->estaAnulado() ? 'anulado' : $vale->estado->value,
            'estado_label' => $vale->situacion(),
            'area' => $vale->area->label(),
            'categoria' => $vale->categoria->nombre,
            'concepto' => $vale->concepto,
            'monto' => (float) $vale->monto,
            'beneficiario' => $vale->beneficiario,
            'placa' => $vale->vehiculo?->placa,
            'solicitado_por' => $vale->solicitante?->name,
            'solicitado' => $vale->created_at?->toIso8601String(),
        ];
    }
}
