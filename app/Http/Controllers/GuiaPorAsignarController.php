<?php

namespace App\Http\Controllers;

use App\Enums\SentidoTramo;
use App\Http\Requests\AsignarGuiaRequest;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\AsignadorVueltas;
use App\Services\OpcionesDeTramo;
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
            ->with(['tracto:id,placa', 'carreta:id,placa', 'conductor:id,nombres,apellidos'])
            ->orderBy('fecha_traslado')
            ->orderBy('id')
            ->get();

        return Inertia::render('vueltas/guias', [
            'pendientes' => $pendientes->map(fn (Viaje $guia): array => $this->pendiente($guia))->all(),
            'sinTicket' => Viaje::query()
                ->whereNotNull('sin_ticket_por')
                ->with(['tracto:id,placa', 'carreta:id,placa', 'conductor:id,nombres,apellidos', 'sinTicketPor:id,name'])
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
            // Las rutas de la tabla, para sumar el combustible de una carga
            // que no estaba programada sin salir de la bandeja.
            'tramos' => fn (): array => app(OpcionesDeTramo::class)->todas(),
        ]);
    }

    public function asignar(AsignarGuiaRequest $request, Viaje $viaje): RedirectResponse
    {
        $this->authorize('asignarGuias', Vuelta::class);

        $vuelta = $request->vuelta();
        // Una carga que no estaba en el programa: entra como parada nueva o
        // reemplaza un tramo que iba vacío.
        $sinProgramar = $request->parada() === null || $request->parada()->esVacio();
        $parada = $this->asignador->asignar(
            $viaje,
            $vuelta,
            $request->parada(),
            $request->enum('sentido', SentidoTramo::class) ?? SentidoTramo::Ida,
            $request->user(),
        );

        // Esa carga casi siempre trae un desvío: se ofrece sumar su
        // combustible ahí mismo, a quien puede editar el ticket.
        if ($sinProgramar && ($request->user()?->can('update', $vuelta) ?? false)) {
            Inertia::flash('carga_sin_programar', [
                'vuelta_id' => $vuelta->id,
                'codigo' => $vuelta->codigo,
                'sentido' => $parada->sentido->value,
                'numero_gr' => $viaje->numero_gr,
                'trayecto' => $this->trayecto($parada),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "GR {$viaje->numero_gr} asignada al ticket {$vuelta->codigo}".match (true) {
                $parada->fuera_de_programa => ' como parada no programada.',
                $sinProgramar => ': reemplaza el tramo que iba vacío.',
                default => '.',
            },
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
        $tickets = $this->asignador->ticketsPara($guia)
            ->load(['conductor:id,nombres,apellidos', 'carreta:id,placa']);

        return [
            ...$this->datosDeGuia($guia),
            'tickets' => $tickets->map(fn (Vuelta $vuelta): array => [
                'id' => $vuelta->id,
                'codigo' => $vuelta->codigo,
                'inicio' => $vuelta->inicio?->toDateString(),
                'estado_label' => $vuelta->estado->label(),
                'conductor_id' => $vuelta->conductor_id,
                'conductor' => $vuelta->conductor?->nombre_completo,
                'carreta' => $vuelta->carreta?->placa,
                'guias' => (int) $vuelta->paradas->sum('viajes_count'),
                // Todo el recorrido, vacíos incluidos, para reconocer el ticket.
                'recorrido' => $vuelta->paradas
                    ->map(fn (ParadaVuelta $parada): array => [
                        'sentido' => $parada->sentido->value,
                        'texto' => $this->trayecto($parada),
                        'carga' => $parada->tipo_carga->label(),
                        'guias' => (int) $parada->viajes_count,
                    ])
                    ->values()
                    ->all(),
                'paradas' => $vuelta->paradas
                    // De los vacíos, solo el que esta GR puede reemplazar.
                    ->filter(fn (ParadaVuelta $parada): bool => ! $parada->esVacio() || $this->asignador->esVacioReemplazable($parada, $guia))
                    ->map(fn (ParadaVuelta $parada): array => [
                        'id' => $parada->id,
                        'sentido' => $parada->sentido->value,
                        'texto' => $this->trayecto($parada),
                        'carga' => $parada->tipo_carga->label(),
                        'guias' => (int) $parada->viajes_count,
                        'no_programada' => $parada->fuera_de_programa,
                        'vacio' => $parada->esVacio(),
                    ])
                    ->values()
                    ->all(),
            ])->all(),
            'sugerencia' => $this->asignador->sugerencia($guia, $tickets),
        ];
    }

    private function trayecto(ParadaVuelta $parada): string
    {
        return ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?');
    }

    /**
     * El conductor sale del padrón si la GR está enlazada; si no, el nombre
     * tal como viene en la GR.
     *
     * @return array{id: int, numero_gr: string, fecha: string, placa: string, carreta: string|null, tiene_unidad: bool, conductor_id: int|null, conductor: string|null, origen: string|null, destino: string|null, carga: string, peso: string, cliente: string, destinatario: string|null}
     */
    private function datosDeGuia(Viaje $guia): array
    {
        return [
            'id' => $guia->id,
            'numero_gr' => $guia->numero_gr,
            'fecha' => $guia->fecha_traslado->toDateString(),
            'placa' => $guia->tracto->placa ?? $guia->placa_tracto,
            'carreta' => $guia->carreta->placa ?? $guia->placa_carreta,
            'tiene_unidad' => $guia->tracto_id !== null,
            'conductor_id' => $guia->conductor_id,
            'conductor' => $guia->conductor?->nombre_completo ?: ($guia->conductor_nombre ?: null),
            'origen' => $this->asignador->lugar($guia->origen),
            'destino' => $this->asignador->lugar($guia->destino),
            'carga' => $guia->tipo_carga->label(),
            'peso' => rtrim(rtrim(number_format((float) $guia->peso, 2, '.', ''), '0'), '.').' '.(strtoupper($guia->unidad_peso) === 'KGM' ? 'kg' : 't'),
            'cliente' => $guia->cliente,
            'destinatario' => $guia->destinatario ?: null,
        ];
    }
}
