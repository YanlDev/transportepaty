<?php

namespace App\Services;

use App\Enums\Moneda;
use App\Models\Cobro;
use App\Models\Factura;
use App\Models\Viaje;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * La cobranza de un vistazo, para la gerencia: qué falta facturar, qué está
 * facturado y sin cobrar, qué ya entró y qué detracciones le toca depositar
 * a la empresa. Todo desde el inicio de la cobranza (`inicio_cobranza`) o de
 * un mes.
 *
 * El mes se lee por lo que pasó en él: las GR que se trasladaron, las
 * facturas que se emitieron y los cobros que entraron. Lo pendiente (por
 * cobrar, vencido, detracciones) es siempre la foto de hoy.
 *
 * @phpstan-type Reporte array{
 *     mes: string|null,
 *     meses: list<array{value: string, label: string}>,
 *     desde: string,
 *     por_facturar: array{guias: int, clientes: list<array<string, mixed>>},
 *     facturado: array{facturas: int, montos: list<array{simbolo: string, monto: float}>},
 *     por_cobrar: array{facturas: int, montos: list<array{simbolo: string, monto: float}>, vencido: list<array{simbolo: string, monto: float}>, facturas_vencidas: int, clientes: list<array<string, mixed>>},
 *     cobrado: array{cobros: int, montos: list<array{simbolo: string, monto: float}>},
 *     detraccion: array{por_depositar: float, facturas_por_depositar: int, sin_cobrar: float, facturas_sin_cobrar: int},
 *     saldo_a_favor: list<array{simbolo: string, monto: float}>,
 *     por_mes: list<array{mes: string, label: string, facturado: float, cobrado: float}>
 * }
 */
class ReporteGerencia
{
    private const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /**
     * @return Reporte
     */
    public function de(?string $mes): array
    {
        $inicio = CarbonImmutable::parse((string) config('transpaty.facturacion.inicio_cobranza'))->startOfDay();
        $hoy = RelojOperativo::fechaDeHoy();
        $desde = $mes === null ? $inicio : CarbonImmutable::parse("{$mes}-01")->startOfMonth()->max($inicio);
        $hasta = $mes === null ? null : CarbonImmutable::parse("{$mes}-01")->endOfMonth();

        // Lo que se factura y se cobra desde el inicio de la cobranza.
        $facturas = Factura::query()
            ->where('fecha_emision', '>=', $inicio->toDateString())
            ->with('clienteDelPadron:id,ruc,alias')
            ->get();
        $delPeriodo = $facturas->filter(fn (Factura $factura): bool => $factura->fecha_emision->gte($desde)
            && ($hasta === null || $factura->fecha_emision->lte($hasta)));
        // En un mes, lo pendiente es lo de las facturas de ese mes.
        $pendientes = $delPeriodo->filter(fn (Factura $factura): bool => $factura->saldo() > 0);
        $vencidas = $pendientes->filter(fn (Factura $factura): bool => ($factura->diasVencida() ?? 0) > 0);

        $cobros = Cobro::query()
            ->where('fecha', '>=', $desde->toDateString())
            ->when($hasta, fn ($query) => $query->where('fecha', '<=', $hasta->toDateString()))
            ->with(['cliente:id,alias,ruc', 'facturas' => fn ($query) => $query->with('clienteDelPadron:id,ruc,alias')])
            ->orderByDesc('fecha')
            ->get();

        $porDepositar = $facturas->filter(fn (Factura $factura): bool => $factura->detraccionPorDepositar());

        return [
            'mes' => $mes,
            'meses' => $this->meses($inicio, $hoy),
            'desde' => $inicio->toDateString(),
            'por_facturar' => $this->porFacturar($desde, $hasta, $hoy),
            'facturado' => [
                'facturas' => $delPeriodo->count(),
                'montos' => $this->porMoneda($delPeriodo, fn (Factura $factura): float => (float) $factura->total),
            ],
            'por_cobrar' => [
                'facturas' => $pendientes->count(),
                'montos' => $this->porMoneda($pendientes, fn (Factura $factura): float => $factura->saldo()),
                'vencido' => $this->porMoneda($vencidas, fn (Factura $factura): float => $factura->saldo()),
                'facturas_vencidas' => $vencidas->count(),
                'tramos' => $this->tramosDeAtraso($pendientes),
                'clientes' => $this->deudaPorCliente($pendientes),
            ],
            'cobrado' => [
                'cobros' => $cobros->count(),
                'montos' => $this->porMoneda($cobros, fn (Cobro $cobro): float => (float) $cobro->monto),
                'clientes' => $this->pagosPorCliente($cobros),
            ],
            'avance' => $this->avance($delPeriodo),
            // Siempre la foto de hoy, sin importar el mes: es lo que la
            // empresa todavía tiene que pasar al Banco de la Nación.
            'detraccion' => [
                'por_depositar' => round((float) $porDepositar->filter(fn (Factura $factura): bool => $factura->fecha_pago !== null)->sum('detraccion'), 2),
                'facturas_por_depositar' => $porDepositar->filter(fn (Factura $factura): bool => $factura->fecha_pago !== null)->count(),
                'sin_cobrar' => round((float) $porDepositar->filter(fn (Factura $factura): bool => $factura->fecha_pago === null)->sum('detraccion'), 2),
                'facturas_sin_cobrar' => $porDepositar->filter(fn (Factura $factura): bool => $factura->fecha_pago === null)->count(),
                // Las que ya toca depositar, la que más espera primero.
                'detalle' => array_values($porDepositar
                    ->filter(fn (Factura $factura): bool => $factura->fecha_pago !== null)
                    ->sortBy('fecha_pago')
                    ->map(fn (Factura $factura): array => [
                        'numero' => $factura->numero,
                        'cliente' => $factura->nombreCliente() ?? 'Sin cliente',
                        'monto' => (float) $factura->detraccion,
                        'fecha_pago' => $factura->fecha_pago?->toDateString(),
                        'dias' => (int) $factura->fecha_pago?->diffInDays($hoy),
                    ])
                    ->all()),
            ],
            'saldo_a_favor' => $this->porMoneda(
                Cobro::query()->with('facturas:id')->get(),
                fn (Cobro $cobro): float => $cobro->saldoAFavor(),
            ),
            'por_mes' => $this->porMes($facturas, $inicio, $hoy),
        ];
    }

    /**
     * Las GR que todavía no tienen factura (sin contar las que se decidió no
     * cobrar), juntas por cliente: cuántas son y desde cuándo espera la más
     * antigua.
     *
     * @return array{guias: int, clientes: list<array<string, mixed>>}
     */
    private function porFacturar(CarbonImmutable $desde, ?CarbonImmutable $hasta, CarbonImmutable $hoy): array
    {
        $guias = Viaje::query()
            ->whereDoesntHave('facturas')
            ->whereNull('no_facturable_at')
            ->where('fecha_traslado', '>=', $desde->toDateString())
            ->when($hasta, fn ($query) => $query->where('fecha_traslado', '<=', $hasta->toDateString()))
            ->with('clienteDelPadron:id,alias')
            ->orderBy('fecha_traslado')
            ->get(['id', 'numero_gr', 'fecha_traslado', 'cliente', 'cliente_ruc', 'cliente_id', 'remitente', 'tipo_carga']);

        $clientes = $guias
            ->groupBy(fn (Viaje $guia): string => $guia->clienteDelPadron->alias ?? ($guia->cliente ?: ($guia->remitente ?: 'Sin cliente')))
            ->map(fn (Collection $delCliente, string $cliente): array => [
                'cliente' => $cliente,
                // Para abrir sus GR en Contabilidad: el RUC o, si no hay, el nombre de la GR.
                'filtro' => $delCliente->first()->cliente_ruc ?: $delCliente->first()->cliente,
                'guias' => $delCliente->count(),
                'mas_antigua' => $delCliente->first()->fecha_traslado->toDateString(),
                'dias' => (int) $delCliente->first()->fecha_traslado->diffInDays($hoy),
                'detalle' => $delCliente->take(20)->map(fn (Viaje $guia): array => [
                    'numero' => $guia->numero_gr,
                    'fecha' => $guia->fecha_traslado->toDateString(),
                    'dias' => (int) $guia->fecha_traslado->diffInDays($hoy),
                    'carga' => $guia->tipo_carga->label(),
                ])->values()->all(),
            ])
            ->sortByDesc('guias')
            ->all();

        return ['guias' => $guias->count(), 'clientes' => array_values($clientes)];
    }

    /**
     * Quién debe y cuánto, del que más al que menos, con sus facturas.
     *
     * @param  Collection<int, Factura>  $pendientes
     * @return list<array<string, mixed>>
     */
    private function deudaPorCliente(Collection $pendientes): array
    {
        $mayor = (float) $pendientes
            ->groupBy(fn (Factura $factura): string => $factura->cliente_ruc ?? ('sin-ruc-'.$factura->cliente_razon_social))
            ->max(fn (Collection $delCliente): float => (float) $delCliente->sum(fn (Factura $factura): float => $factura->saldo()));

        return array_values($pendientes
            ->groupBy(fn (Factura $factura): string => $factura->cliente_ruc ?? ('sin-ruc-'.$factura->cliente_razon_social))
            ->map(function (Collection $delCliente) use ($mayor): array {
                $vencidas = $delCliente->filter(fn (Factura $factura): bool => ($factura->diasVencida() ?? 0) > 0);
                $debe = (float) $delCliente->sum(fn (Factura $factura): float => $factura->saldo());

                return [
                    'cliente' => $delCliente->first()->nombreCliente() ?? 'Sin cliente',
                    'filtro' => $delCliente->first()->cliente_ruc ?: $delCliente->first()->cliente_razon_social,
                    // Cuánto pesa frente al que más debe: el largo de su barra.
                    'parte' => $mayor > 0 ? round($debe / $mayor, 4) : 0.0,
                    'facturas' => $delCliente->count(),
                    'montos' => $this->porMoneda($delCliente, fn (Factura $factura): float => $factura->saldo()),
                    'orden' => (float) $delCliente->sum(fn (Factura $factura): float => $factura->saldo()),
                    'vencido' => $this->porMoneda($vencidas, fn (Factura $factura): float => $factura->saldo()),
                    'dias_vencida' => (int) $vencidas->max(fn (Factura $factura): int => $factura->diasVencida() ?? 0),
                    // Las que más llevan vencidas, primero.
                    'detalle' => $delCliente->sortByDesc(fn (Factura $factura): int => $factura->diasVencida() ?? -9999)->take(20)->map(fn (Factura $factura): array => [
                        'numero' => $factura->numero,
                        'fecha' => $factura->fecha_emision->toDateString(),
                        'simbolo' => $factura->moneda->simbolo(),
                        'saldo' => $factura->saldo(),
                        'parcial' => (float) $factura->cobrado > 0,
                        'vence' => $factura->fechaVencimiento()->toDateString(),
                        'dias_vencida' => $factura->diasVencida(),
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('orden')
            ->all());
    }

    /**
     * Lo por cobrar en soles según su atraso: al día, y vencido hasta 30,
     * hasta 60 y más de 60 días.
     *
     * @param  Collection<int, Factura>  $pendientes
     * @return list<array{tramo: string, facturas: int, monto: float, vencido: bool}>
     */
    private function tramosDeAtraso(Collection $pendientes): array
    {
        $soles = $pendientes->filter(fn (Factura $factura): bool => $factura->moneda === Moneda::Soles);
        $tramos = ['Al día' => [null, 0], '1 a 30 días' => [1, 30], '31 a 60 días' => [31, 60], 'Más de 60' => [61, null]];

        return array_map(function (string $tramo, array $limites) use ($soles): array {
            [$desde, $hasta] = $limites;
            $delTramo = $soles->filter(function (Factura $factura) use ($desde, $hasta): bool {
                $dias = $factura->diasVencida() ?? 0;

                return ($desde === null || $dias >= $desde) && ($hasta === null || $dias <= $hasta);
            });

            return [
                'tramo' => $tramo,
                'facturas' => $delTramo->count(),
                'monto' => round((float) $delTramo->sum(fn (Factura $factura): float => $factura->saldo()), 2),
                'vencido' => $desde !== null,
            ];
        }, array_keys($tramos), array_values($tramos));
    }

    /**
     * De lo facturado en el período (en soles, lo que paga el cliente):
     * cuánto ya entró, cuánto falta y está al día, y cuánto está vencido.
     *
     * @param  Collection<int, Factura>  $facturas
     * @return array{facturado: float, cobrado: float, al_dia: float, vencido: float}
     */
    private function avance(Collection $facturas): array
    {
        $soles = $facturas->filter(fn (Factura $factura): bool => $factura->moneda === Moneda::Soles);
        $vencido = (float) $soles
            ->filter(fn (Factura $factura): bool => ($factura->diasVencida() ?? 0) > 0)
            ->sum(fn (Factura $factura): float => $factura->saldo());
        $pendiente = (float) $soles->sum(fn (Factura $factura): float => $factura->saldo());

        return [
            'facturado' => round((float) $soles->sum(fn (Factura $factura): float => (float) $factura->neto), 2),
            'cobrado' => round((float) $soles->sum(fn (Factura $factura): float => $factura->montoCobrado()), 2),
            'al_dia' => round($pendiente - $vencido, 2),
            'vencido' => round($vencido, 2),
        ];
    }

    /**
     * Quién pagó en el período y cuánto, con las facturas que pagó cada
     * depósito. El cliente es el del cobro o, si no lo dice, el de su factura.
     *
     * @param  Collection<int, Cobro>  $cobros
     * @return list<array<string, mixed>>
     */
    private function pagosPorCliente(Collection $cobros): array
    {
        return array_values($cobros
            ->groupBy(fn (Cobro $cobro): string => $cobro->cliente->alias
                ?? $cobro->facturas->first()?->nombreCliente()
                ?? 'Sin cliente')
            ->map(fn (Collection $delCliente, string $cliente): array => [
                'cliente' => $cliente,
                'cobros' => $delCliente->count(),
                'montos' => $this->porMoneda($delCliente, fn (Cobro $cobro): float => (float) $cobro->monto),
                'orden' => (float) $delCliente->sum(fn (Cobro $cobro): float => (float) $cobro->monto),
                'ultimo' => $delCliente->max(fn (Cobro $cobro): string => $cobro->fecha->toDateString()),
                'detalle' => $this->facturasPagadas($delCliente->take(40)),
            ])
            ->sortByDesc('orden')
            ->all());
    }

    /**
     * Las facturas que pagaron esos cobros, con lo que le tocó a cada una.
     *
     * @param  Collection<int, Cobro>  $cobros
     * @return list<array{numero: string, fecha: string, simbolo: string, monto: float, operacion: string|null}>
     */
    private function facturasPagadas(Collection $cobros): array
    {
        $pagadas = [];

        foreach ($cobros as $cobro) {
            foreach ($cobro->facturas as $factura) {
                $pagadas[] = [
                    'numero' => $factura->numero,
                    'fecha' => $cobro->fecha->toDateString(),
                    'simbolo' => $cobro->moneda->simbolo(),
                    'monto' => (float) $factura->pivot?->getAttribute('monto'),
                    'operacion' => $cobro->numero_operacion,
                ];
            }
        }

        return $pagadas;
    }

    /**
     * Lo facturado y lo cobrado de cada mes, en soles, para ver la marcha.
     *
     * @param  Collection<int, Factura>  $facturas
     * @return list<array{mes: string, label: string, facturado: float, cobrado: float}>
     */
    private function porMes(Collection $facturas, CarbonImmutable $inicio, CarbonImmutable $hoy): array
    {
        $cobros = Cobro::query()
            ->where('fecha', '>=', $inicio->toDateString())
            ->where('moneda', Moneda::Soles)
            ->get(['monto', 'fecha']);

        return array_map(fn (array $mes): array => [
            'mes' => $mes['value'],
            'label' => $mes['label'],
            'facturado' => round((float) $facturas
                ->filter(fn (Factura $factura): bool => $factura->moneda === Moneda::Soles && $factura->fecha_emision->format('Y-m') === $mes['value'])
                ->sum(fn (Factura $factura): float => (float) $factura->total), 2),
            'cobrado' => round((float) $cobros
                ->filter(fn (Cobro $cobro): bool => $cobro->fecha->format('Y-m') === $mes['value'])
                ->sum(fn (Cobro $cobro): float => (float) $cobro->monto), 2),
        ], array_reverse($this->meses($inicio, $hoy)));
    }

    /**
     * Los meses desde el inicio de la cobranza hasta hoy, el más reciente primero.
     *
     * @return list<array{value: string, label: string}>
     */
    private function meses(CarbonImmutable $inicio, CarbonImmutable $hoy): array
    {
        $meses = [];

        for ($mes = $hoy->startOfMonth(); $mes->gte($inicio->startOfMonth()); $mes = $mes->subMonth()) {
            $meses[] = ['value' => $mes->format('Y-m'), 'label' => self::MESES[$mes->month].' '.$mes->year];
        }

        return $meses;
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @param  callable(T): float  $monto
     * @return list<array{simbolo: string, monto: float}>
     */
    private function porMoneda(Collection $items, callable $monto): array
    {
        return array_values($items
            ->groupBy(fn ($item): string => $item->moneda->value)
            ->map(fn (Collection $deLaMoneda, string $moneda): array => [
                'simbolo' => Moneda::from($moneda)->simbolo(),
                'monto' => round((float) $deLaMoneda->sum($monto), 2),
            ])
            ->filter(fn (array $fila): bool => $fila['monto'] > 0)
            // Los soles primero.
            ->sortBy(fn (array $fila): int => $fila['simbolo'] === Moneda::Soles->simbolo() ? 0 : 1)
            ->all());
    }
}
