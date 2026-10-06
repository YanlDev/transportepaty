<?php

namespace App\Http\Controllers;

use App\Services\RelojOperativo;
use App\Services\ResumenTablero;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ResumenTablero $resumen) {}

    public function index(Request $request): Response
    {
        $this->authorize('ver-tablero');

        $rango = $this->rangoPedido($request);

        return Inertia::render('dashboard', [
            'rango' => $rango,
            ...$this->resumen->paraElRango($rango['desde'], $rango['hasta']),
        ]);
    }

    /**
     * Cuántos meses hacia atrás ofrece el selector de mes.
     */
    private const MESES_ELEGIBLES = 12;

    /**
     * El rango que se está mirando. Se pide por preset («este mes», «últimos
     * 3 meses», «este año») y no por fechas sueltas porque es como se habla
     * del período en la operación; `desde`/`hasta` viajan igual al frontend
     * para mostrarlos y para poder afinarlos a mano más adelante. Con
     * `periodo=mes` se puede pedir además un mes cerrado (`mes=2026-08`)
     * para comparar meses entre sí; si no es uno de los ofrecidos, cae al
     * mes en curso.
     *
     * Se queda en el controlador —y no en el servicio— porque es lo único de
     * todo el tablero que sale de la petición.
     *
     * @return array{periodo: string, desde: string, hasta: string, mes: string|null, meses: list<array{valor: string, label: string}>}
     */
    private function rangoPedido(Request $request): array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $periodo = $request->string('periodo')->value();
        $meses = $this->mesesElegibles($hoy);
        $mes = $request->string('mes')->value();
        $mes = in_array($mes, array_column($meses, 'valor'), true) ? $mes : $hoy->format('Y-m');

        [$desde, $hasta] = match ($periodo) {
            'trimestre' => [$hoy->subMonths(2)->startOfMonth(), $hoy->endOfMonth()],
            'anio' => [$hoy->startOfYear(), $hoy->endOfYear()],
            default => [
                CarbonImmutable::parse("{$mes}-01")->startOfMonth(),
                CarbonImmutable::parse("{$mes}-01")->endOfMonth(),
            ],
        };

        $periodo = in_array($periodo, ['trimestre', 'anio'], true) ? $periodo : 'mes';

        return [
            'periodo' => $periodo,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'mes' => $periodo === 'mes' ? $mes : null,
            'meses' => $meses,
        ];
    }

    /**
     * El mes en curso y los anteriores, del más reciente al más viejo.
     *
     * @return list<array{valor: string, label: string}>
     */
    private function mesesElegibles(CarbonImmutable $hoy): array
    {
        $meses = [];

        for ($atras = 0; $atras < self::MESES_ELEGIBLES; $atras++) {
            $mes = $hoy->startOfMonth()->subMonths($atras);

            $meses[] = [
                'valor' => $mes->format('Y-m'),
                'label' => ucfirst($mes->locale('es')->translatedFormat('F Y')),
            ];
        }

        return $meses;
    }
}
