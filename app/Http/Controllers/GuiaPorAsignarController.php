<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Http\Requests\AsignarGuiaRequest;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AsignadorVueltas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La bandeja de las GR que todavía no están en un ticket. Ninguna GR entra
 * sola: el sistema sugiere el ticket abierto de su unidad y la parada, y
 * quien programa confirma. La que no va en ningún ticket se marca «sin
 * ticket» con su motivo.
 */
class GuiaPorAsignarController extends Controller
{
    /** Cuántas de las marcadas «sin ticket» se muestran, de las últimas. */
    private const SIN_TICKET_RECIENTES = 30;

    public function __construct(private readonly AsignadorVueltas $asignador) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vuelta::class);

        $pendientes = Viaje::query()
            ->whereNull('parada_vuelta_id')
            ->whereNull('sin_ticket_at')
            ->with('tracto:id,placa')
            ->orderBy('fecha_traslado')
            ->orderBy('id')
            ->get();

        return Inertia::render('vueltas/guias', [
            'pendientes' => $pendientes->map(fn (Viaje $guia): array => $this->pendiente($guia))->all(),
            'sinTicket' => Viaje::query()
                ->whereNotNull('sin_ticket_por')
                ->with('sinTicketPor:id,name')
                ->latest('sin_ticket_at')
                ->limit(self::SIN_TICKET_RECIENTES)
                ->get()
                ->map(fn (Viaje $guia): array => [
                    ...$this->datosDeGuia($guia),
                    'motivo' => $guia->motivo_sin_ticket,
                    'por' => $guia->sinTicketPor?->name,
                    'en' => $guia->sin_ticket_at?->format('Y-m-d H:i'),
                ])
                ->all(),
            'puedeAsignar' => $request->user()?->can('asignarGuias', Vuelta::class) ?? false,
        ]);
    }

    public function asignar(AsignarGuiaRequest $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('asignarGuias', Vuelta::class);

        $vuelta = $request->vuelta();
        $parada = $this->asignador->asignar(
            $viaje,
            $vuelta,
            $request->parada(),
            $request->enum('sentido', SentidoTramo::class) ?? SentidoTramo::Ida,
            $request->user(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "GR {$viaje->numero_gr} asignada al ticket {$vuelta->codigo}".($parada->fuera_de_programa ? ' como parada no programada.' : '.'),
        ]);
    }

    /**
     * Saca la GR de su ticket y la devuelve a la bandeja.
     */
    public function quitar(Viaje $viaje): RedirectResponse
    {
        $this->authorize('asignarGuias', Vuelta::class);

        $vuelta = $viaje->parada?->vuelta;

        if ($vuelta !== null && ! $vuelta->estaAbierta()) {
            return back()->with('toast', ['type' => 'error', 'message' => "El ticket {$vuelta->codigo} está cerrado: reábrelo para sacarle una GR."]);
        }

        $this->asignador->quitar($viaje);

        return back()->with('toast', ['type' => 'success', 'message' => "GR {$viaje->numero_gr} devuelta a la bandeja."]);
    }

    public function sinTicket(Request $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('asignarGuias', Vuelta::class);

        $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

        if ($viaje->parada_vuelta_id !== null) {
            return back()->with('toast', ['type' => 'error', 'message' => "La GR {$viaje->numero_gr} está en un ticket: sácala antes."]);
        }

        $this->asignador->marcarSinTicket($viaje, $datos['motivo'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "GR {$viaje->numero_gr} marcada sin ticket."]);
    }

    public function devolver(Viaje $viaje): RedirectResponse
    {
        $this->authorize('asignarGuias', Vuelta::class);

        $this->asignador->devolverABandeja($viaje);

        return back()->with('toast', ['type' => 'success', 'message' => "GR {$viaje->numero_gr} devuelta a la bandeja."]);
    }

    /**
     * Una GR de la bandeja con los tickets abiertos de su unidad y lo que el
     * sistema sugiere.
     *
     * @return array<string, mixed>
     */
    private function pendiente(Viaje $guia): array
    {
        $tickets = $this->asignador->ticketsPara($guia);

        return [
            ...$this->datosDeGuia($guia),
            'tickets' => $tickets->map(fn (Vuelta $vuelta): array => [
                'id' => $vuelta->id,
                'codigo' => $vuelta->codigo,
                'inicio' => $vuelta->inicio?->toDateString(),
                'estado_label' => $vuelta->estado->label(),
                'paradas' => $vuelta->paradas
                    ->reject(fn (ParadaVuelta $parada): bool => $parada->esVacio())
                    ->map(fn (ParadaVuelta $parada): array => [
                        'id' => $parada->id,
                        'sentido' => $parada->sentido->value,
                        'texto' => ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?'),
                        'carga' => $parada->tipo_carga->label(),
                        'guias' => (int) $parada->viajes_count,
                        'no_programada' => $parada->fuera_de_programa,
                    ])
                    ->values()
                    ->all(),
            ])->all(),
            'sugerencia' => $this->asignador->sugerencia($guia, $tickets),
        ];
    }

    /**
     * @return array{id: int, numero_gr: string, fecha: string, placa: string, tiene_unidad: bool, origen: string|null, destino: string|null, carga: string, peso: string, cliente: string}
     */
    private function datosDeGuia(Viaje $guia): array
    {
        return [
            'id' => $guia->id,
            'numero_gr' => $guia->numero_gr,
            'fecha' => $guia->fecha_traslado->toDateString(),
            'placa' => $guia->tracto->placa ?? $guia->placa_tracto,
            'tiene_unidad' => $guia->tracto_id !== null,
            'origen' => $this->asignador->lugar($guia->origen),
            'destino' => $this->asignador->lugar($guia->destino),
            'carga' => $guia->tipo_carga->label(),
            'peso' => rtrim(rtrim(number_format((float) $guia->peso, 2, '.', ''), '0'), '.').' '.(strtoupper($guia->unidad_peso) === 'KGM' ? 'kg' : 't'),
            'cliente' => $guia->cliente,
        ];
    }
}
