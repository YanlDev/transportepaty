<?php

namespace App\Services;

use App\Enums\EstadoCobranza;
use App\Enums\Moneda;
use App\Models\Factura;
use App\Models\Viaje;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La lectura de los viajes desde el dinero: qué cae bajo los filtros de la
 * cobranza, cuánto hay por cobrar y cuánto falta facturar.
 *
 * Vive fuera del controlador por lo mismo que `ResumenTablero`: traducir un
 * estado de cobranza a la presencia de `factura_id` y de `fecha_pago`, o
 * decidir que los totales se suman por factura y no por fila, son reglas del
 * negocio y no de la petición.
 *
 * @phpstan-type FiltrosCobranza array{buscar: string|null, cliente: string|null, estado: string|null, mes: string|null, desde: string|null, hasta: string|null}
 */
class ResumenCobranza
{
    /**
     * Cuántos meses se desglosan en el resumen de «por facturar». Con más, la
     * fila de arriba compite con la tabla; el resto se alcanza por el filtro.
     */
    private const MESES_EN_RESUMEN = 4;

    /**
     * Los meses escritos acá y no sacados del locale de Carbon: el de la
     * aplicación está en inglés, y cambiarlo por una etiqueta de filtro
     * movería las fechas de todo lo demás.
     *
     * @var array<int, string>
     */
    public const MESES = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Los viajes que caen bajo los filtros, sin nada cargado encima: es la
     * base que comparten la tabla, los totales y el desglose por mes, y cada
     * una le agrega lo suyo.
     *
     * Los filtros de estado no son una columna: se traducen a la presencia de
     * `factura_id` y de `fecha_pago`, que son los dos únicos hechos guardados.
     *
     * @param  FiltrosCobranza  $filtros
     * @return Builder<Viaje>
     */
    public function consulta(array $filtros): Builder
    {
        return Viaje::query()
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('numero_gr', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('cliente', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('destinatario', "%{$buscar}%", caseSensitive: false)
                        ->orWhereHas('factura', fn ($query) => $query->whereLike('numero', "%{$buscar}%", caseSensitive: false));
                });
            })
            ->when($filtros['cliente'], fn ($query, string $cliente) => $query->where('cliente', $cliente))
            ->when($filtros['mes'], function ($query, string $mes): void {
                // `Y-m` acotado a los días del mes en vez de `whereYear` +
                // `whereMonth`: así el índice de `fecha_traslado` sigue
                // sirviendo, que con 450 filas da igual pero no dentro de un año.
                $inicio = CarbonImmutable::parse("{$mes}-01")->startOfMonth();

                $query->whereBetween('fecha_traslado', [
                    $inicio->toDateString(),
                    $inicio->endOfMonth()->toDateString(),
                ]);
            })
            ->when($filtros['desde'], fn ($query, string $desde) => $query->where('fecha_traslado', '>=', $desde))
            ->when($filtros['hasta'], fn ($query, string $hasta) => $query->where('fecha_traslado', '<=', $hasta))
            ->when($filtros['estado'], function ($query, string $estado): void {
                match ($estado) {
                    EstadoCobranza::SinFacturar->value => $query->whereNull('factura_id'),
                    EstadoCobranza::Facturado->value => $query->whereHas('factura', fn ($query) => $query->whereNull('fecha_pago')),
                    EstadoCobranza::Pagado->value => $query->whereHas('factura', fn ($query) => $query->whereNotNull('fecha_pago')),
                    default => null,
                };
            });
    }

    /**
     * Lo que el contador viene a ver: cuánto hay por cobrar y cuánto ya entró,
     * bajo los mismos filtros de la tabla. Separado por moneda porque sumar
     * soles con dólares da un número que no significa nada.
     *
     * Los totales se calculan sobre facturas distintas, no sobre filas: una
     * factura que cubre tres viajes aparece en tres filas y sumarla tres veces
     * inflaría el saldo.
     *
     * @param  FiltrosCobranza  $filtros
     * @return array<string, mixed>
     */
    public function totales(array $filtros): array
    {
        $facturas = Factura::query()
            ->whereIn('id', $this->consulta($filtros)->whereNotNull('factura_id')->select('factura_id'))
            ->get(['id', 'monto', 'moneda', 'fecha_pago']);

        $porMoneda = $facturas
            ->groupBy(fn (Factura $factura): string => $factura->moneda->value)
            ->map(fn ($grupo, string $moneda): array => [
                'moneda' => $moneda,
                'simbolo' => Moneda::from($moneda)->simbolo(),
                'por_cobrar' => (float) $grupo->whereNull('fecha_pago')->sum('monto'),
                'cobrado' => (float) $grupo->whereNotNull('fecha_pago')->sum('monto'),
            ])
            ->values()
            ->all();

        return [
            'montos' => $porMoneda,
            'por_facturar' => $this->porFacturarPorMes($filtros),
            'facturas' => $facturas->count(),
            // Una factura registrada sin monto suma cero y desaparecería del
            // total sin que nadie lo note; se cuenta aparte para que se vea.
            'sin_monto' => $facturas->whereNull('monto')->count(),
        ];
    }

    /**
     * Los meses que tienen viajes, del más reciente al más antiguo. Se derivan
     * de las fechas —no hay tabla de periodos— igual que las ciudades salen de
     * las direcciones; se piden las fechas distintas y no las filas para que
     * el mapeo en PHP no crezca con el historial.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function opcionesDeMes(): array
    {
        return Viaje::query()
            ->select('fecha_traslado')
            ->distinct()
            ->pluck('fecha_traslado')
            ->map(fn (CarbonImmutable|Carbon $fecha): string => $fecha->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (string $mes): array => [
                'value' => $mes,
                'label' => self::etiquetaDeMes($mes),
            ])
            ->all();
    }

    /**
     * Lo que falta facturar, desglosado por mes. Un solo número global no dice
     * nada útil cuando arrastra medio año: la pregunta real es cuánto de
     * setiembre queda por facturar, porque ese es el trabajo de la semana.
     *
     * Se traen solo las fechas —no las filas— y se agrupan en PHP, igual que
     * las ciudades y los meses del filtro: no hay función de mes portable
     * entre el Postgres de producción y el SQLite de los tests.
     *
     * @param  FiltrosCobranza  $filtros
     * @return array<int, array{mes: string, label: string, viajes: int}>
     */
    private function porFacturarPorMes(array $filtros): array
    {
        return $this->consulta($filtros)
            ->whereNull('factura_id')
            ->reorder()
            ->pluck('fecha_traslado')
            ->groupBy(fn (CarbonImmutable|Carbon $fecha): string => $fecha->format('Y-m'))
            ->map(fn (Collection $delMes, string $mes): array => [
                'mes' => $mes,
                'label' => self::etiquetaDeMes($mes),
                'viajes' => $delMes->count(),
            ])
            ->sortKeysDesc()
            // Los meses viejos sin facturar siguen contando en su propio
            // filtro; acá sobrarían: el ancho es para lo que está en curso.
            ->take(self::MESES_EN_RESUMEN)
            ->values()
            ->all();
    }

    /**
     * «2026-09» leído como «Septiembre 2026». El idioma se fija acá y no se
     * hereda del locale de la app, que está en inglés.
     */
    private static function etiquetaDeMes(string $mes): string
    {
        return self::MESES[(int) substr($mes, 5, 2)].' '.substr($mes, 0, 4);
    }
}
