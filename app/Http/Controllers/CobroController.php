<?php

namespace App\Http\Controllers;

use App\Enums\Moneda;
use App\Enums\Permiso;
use App\Http\Requests\AnularCobroRequest;
use App\Http\Requests\AplicarCobroRequest;
use App\Http\Requests\RegistrarCobroRequest;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\CuentaBancaria;
use App\Models\Factura;
use App\Services\RegistroDeCobros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los cobros: la plata que entró, depósito por depósito, y qué facturas pagó
 * cada uno. Desde acá se registra un cobro que paga varias facturas, uno
 * parcial o un adelanto (saldo a favor), se reparte ese saldo cuando sale la
 * factura y se anula el que se registró mal.
 */
class CobroController extends Controller
{
    public function __construct(private readonly RegistroDeCobros $registro) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cobro::class);

        $soloConSaldo = $request->boolean('con_saldo');
        $puedeAnular = $request->user()?->checkPermissionTo(Permiso::CobranzaAnular) ?? false;
        $puedeGestionar = $request->user()?->checkPermissionTo(Permiso::CobranzaGestionar) ?? false;

        $cobros = Cobro::query()
            ->conAnulados()
            ->with(['facturas:id,numero', 'cuentaBancaria:id,alias,tipo', 'cliente:id,alias', 'anuladoPor:id,name', 'usuario:id,name'])
            // Con saldo a favor: lo que entró y todavía no paga ninguna factura.
            ->when($soloConSaldo, fn ($query) => $query
                ->whereNull('anulado_at')
                ->whereRaw('cobros.monto > (select coalesce(sum(cobro_factura.monto), 0) from cobro_factura where cobro_factura.cobro_id = cobros.id)'))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Cobro $cobro): array => [
                'id' => $cobro->id,
                'fecha' => $cobro->fecha->toDateString(),
                'cuenta' => $cobro->cuentaBancaria?->alias,
                'numero_operacion' => $cobro->numero_operacion,
                'cliente_id' => $cobro->cliente_id,
                'cliente' => $cobro->cliente?->alias,
                'monto' => (float) $cobro->monto,
                'moneda' => $cobro->moneda->value,
                'simbolo' => $cobro->moneda->simbolo(),
                'aplicado' => $cobro->aplicado(),
                'saldo_a_favor' => $cobro->saldoAFavor(),
                'facturas' => $cobro->facturas->map(fn (Factura $factura): array => [
                    'id' => $factura->id,
                    'numero' => $factura->numero,
                    'monto' => $factura->montoDelCobro(),
                ])->values()->all(),
                'observacion' => $cobro->observacion,
                'registrado_por' => $cobro->usuario?->name,
                'anulado' => $cobro->estaAnulado(),
                'anulado_el' => $cobro->anulado_at?->toDateString(),
                'anulado_por' => $cobro->anuladoPor?->name,
                'motivo_anulacion' => $cobro->motivo_anulacion,
                'aplicable' => $puedeGestionar && ! $cobro->estaAnulado() && $cobro->saldoAFavor() > 0,
                'anulable' => $puedeAnular && ! $cobro->estaAnulado(),
            ]);

        return Inertia::render('contabilidad/cobros', [
            'cobros' => $cobros,
            'filtros' => ['con_saldo' => $soloConSaldo],
            'saldoAFavor' => self::saldoAFavorTotal(),
            ...self::catalogos(),
        ]);
    }

    public function store(RegistrarCobroRequest $request): RedirectResponse
    {
        $this->authorize('create', Cobro::class);

        $cobro = $this->registro->registrar($request->datos(), $request->aplicaciones(), $request->user());
        $cobro->load('facturas');
        $saldo = $cobro->saldoAFavor();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cobro registrado'
                .($cobro->facturas->count() > 0 ? ': paga '.($cobro->facturas->count() === 1 ? '1 factura' : "{$cobro->facturas->count()} facturas") : '')
                .($saldo > 0 ? '. Quedan '.$cobro->moneda->simbolo().' '.number_format($saldo, 2).' a favor del cliente.' : '.'),
        ]);
    }

    /**
     * Reparte el saldo a favor de un cobro entre más facturas.
     */
    public function aplicar(AplicarCobroRequest $request, Cobro $cobro): RedirectResponse
    {
        $this->authorize('aplicar', $cobro);

        $this->registro->aplicar($cobro, $request->aplicaciones());

        return back()->with('toast', ['type' => 'success', 'message' => 'Saldo aplicado.']);
    }

    public function anular(AnularCobroRequest $request, Cobro $cobro): RedirectResponse
    {
        $this->authorize('anular', $cobro);

        $this->registro->anular($cobro, $request->string('motivo')->trim()->toString(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Cobro anulado: sus facturas vuelven a quedar por cobrar.']);
    }

    /**
     * Lo que necesita el formulario de un cobro: las cuentas, los clientes y
     * las facturas que todavía deben algo.
     *
     * @return array<string, \Closure>
     */
    public static function catalogos(): array
    {
        return [
            'cuentasDeCobro' => fn (): array => CuentaBancaria::query()
                ->where('activa', true)
                ->orderBy('alias')
                ->get(['id', 'alias', 'moneda'])
                ->map(fn (CuentaBancaria $cuenta): array => ['id' => $cuenta->id, 'alias' => $cuenta->alias, 'moneda' => $cuenta->moneda->value])
                ->all(),
            'clientesDeCobro' => fn (): array => Cliente::query()
                ->orderBy('alias')
                ->get(['id', 'alias', 'ruc'])
                ->map(fn (Cliente $cliente): array => ['id' => $cliente->id, 'alias' => $cliente->alias, 'ruc' => $cliente->ruc])
                ->all(),
            'facturasPorCobrar' => fn (): array => Factura::query()
                ->whereNull('fecha_pago')
                ->whereNotNull('neto')
                ->orderBy('fecha_emision')
                ->orderBy('id')
                ->get(['id', 'numero', 'fecha_emision', 'cliente_ruc', 'cliente_razon_social', 'neto', 'cobrado', 'moneda', 'fecha_pago'])
                ->filter(fn (Factura $factura): bool => $factura->saldo() > 0)
                ->map(fn (Factura $factura): array => [
                    'id' => $factura->id,
                    'numero' => $factura->numero,
                    'fecha_emision' => $factura->fecha_emision->toDateString(),
                    'cliente_ruc' => $factura->cliente_ruc,
                    'cliente' => $factura->cliente_razon_social,
                    'neto' => (float) $factura->neto,
                    'saldo' => $factura->saldo(),
                    'moneda' => $factura->moneda->value,
                ])
                ->values()
                ->all(),
            'monedasDeCobro' => fn (): array => Moneda::options(),
        ];
    }

    /**
     * Cuánta plata entró y todavía no paga ninguna factura, por moneda.
     *
     * @return list<array{moneda: string, simbolo: string, monto: float}>
     */
    private static function saldoAFavorTotal(): array
    {
        return array_values(Cobro::query()
            ->with('facturas:id')
            ->get()
            ->groupBy(fn (Cobro $cobro): string => $cobro->moneda->value)
            ->map(fn ($cobros, string $moneda): array => [
                'moneda' => $moneda,
                'simbolo' => Moneda::from($moneda)->simbolo(),
                'monto' => round((float) $cobros->sum(fn (Cobro $cobro): float => $cobro->saldoAFavor()), 2),
            ])
            ->filter(fn (array $fila): bool => $fila['monto'] > 0)
            ->all());
    }
}
