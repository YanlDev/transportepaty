<?php

namespace App\Http\Controllers;

use App\Services\RelojOperativo;
use App\Services\ResumenTablero;
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
     * El rango que se está mirando. Se pide por preset («este mes», «últimos
     * 3 meses», «este año») y no por fechas sueltas porque es como se habla
     * del período en la operación; `desde`/`hasta` viajan igual al frontend
     * para mostrarlos y para poder afinarlos a mano más adelante.
     *
     * Se queda en el controlador —y no en el servicio— porque es lo único de
     * todo el tablero que sale de la petición.
     *
     * @return array{periodo: string, desde: string, hasta: string}
     */
    private function rangoPedido(Request $request): array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $periodo = $request->string('periodo')->value();

        [$desde, $hasta] = match ($periodo) {
            'trimestre' => [$hoy->subMonths(2)->startOfMonth(), $hoy->endOfMonth()],
            'anio' => [$hoy->startOfYear(), $hoy->endOfYear()],
            default => [$hoy->startOfMonth(), $hoy->endOfMonth()],
        };

        return [
            'periodo' => in_array($periodo, ['trimestre', 'anio'], true) ? $periodo : 'mes',
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
        ];
    }
}
