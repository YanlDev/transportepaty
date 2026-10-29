<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Services\PendientesDelInicio;
use App\Services\RelojOperativo;
use App\Services\ResumenTablero;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ResumenTablero $resumen,
        private readonly PendientesDelInicio $pendientes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('ver-tablero');

        $rango = $this->rangoPedido($request);

        return Inertia::render('dashboard', [
            'rango' => $rango,
            ...$this->resumen->paraElRango($rango['desde'], $rango['hasta']),
            'metaConcentrado' => $this->resumen->metaConcentrado($rango['mes']),
            // Lo que le toca a cada quien hoy, según sus permisos.
            'pendientes' => fn (): array => $request->user() ? $this->pendientes->para($request->user()) : [],
        ]);
    }

    /**
     * El mes que se está mirando: el que llega en `mes=2026-09` si es uno de
     * los ofrecidos, y si no el mes en curso. Todo el tablero —gráficos y
     * meta— lee ese mes.
     *
     * Se queda en el controlador —y no en el servicio— porque es lo único de
     * todo el tablero que sale de la petición.
     *
     * @return array{desde: string, hasta: string, mes: string, meses: list<array{valor: string, label: string}>}
     */
    private function rangoPedido(Request $request): array
    {
        $meses = $this->mesesElegibles(RelojOperativo::fechaDeHoy());
        $mes = $request->string('mes')->value();
        $mes = in_array($mes, array_column($meses, 'valor'), true) ? $mes : $meses[0]['valor'];
        $inicio = CarbonImmutable::parse("{$mes}-01");

        return [
            'desde' => $inicio->startOfMonth()->toDateString(),
            'hasta' => $inicio->endOfMonth()->toDateString(),
            'mes' => $mes,
            'meses' => $meses,
        ];
    }

    /**
     * Del mes en curso hacia atrás hasta el primer mes con viajes: un mes
     * anterior a eso no tiene nada que mostrar.
     *
     * @return list<array{valor: string, label: string}>
     */
    private function mesesElegibles(CarbonImmutable $hoy): array
    {
        $primerViaje = Viaje::query()->min('fecha_traslado');
        $desde = $primerViaje === null
            ? $hoy->startOfMonth()
            : CarbonImmutable::parse($primerViaje)->startOfMonth();

        $meses = [];

        for ($mes = $hoy->startOfMonth(); $mes->gte($desde); $mes = $mes->subMonth()) {
            $meses[] = [
                'valor' => $mes->format('Y-m'),
                'label' => ucfirst($mes->settings(['locale' => 'es'])->translatedFormat('F Y')),
            ];
        }

        return $meses;
    }
}
