<?php

namespace App\Services;

use App\Enums\ResultadoTaller;
use App\Enums\TipoAlerta;
use App\Models\ParadaVuelta;
use App\Models\RevisionTaller;
use App\Models\User;
use App\Models\Vuelta;

/**
 * Las alertas del circuito del ticket, con su texto: qué unidad, quién la
 * maneja y a dónde va, para que se entienda sin abrir la app.
 */
class AlertasDelTicket
{
    public function __construct(private readonly Alertas $alertas) {}

    public function programado(Vuelta $vuelta, ?User $autor): void
    {
        $this->alertas->avisar(
            TipoAlerta::TicketProgramado,
            "Ticket {$vuelta->codigo} programado: falta revisión de taller",
            $this->resumen($vuelta),
            route('taller.index'),
            $autor,
            whatsapp: $this->paraTaller($vuelta),
        );
    }

    /**
     * El aviso a taller por WhatsApp: qué ticket, cuándo sale, con qué
     * unidad y conductor, por dónde, y el enlace para dar el apto.
     */
    public function paraTaller(Vuelta $vuelta): string
    {
        $vuelta->loadMissing(['tracto:id,placa', 'carreta:id,placa', 'conductor:id,nombres,apellidos', 'paradas']);
        $primera = $vuelta->paradas->first();
        $ultima = $vuelta->paradas->last();
        // «dom 22/11», sin el punto de la abreviatura.
        $sale = $vuelta->inicio === null ? null : str_replace('.', '', $vuelta->inicio->locale('es')->isoFormat('ddd DD/MM'));

        return implode("\n", array_filter([
            '🔧 *Revisión de taller pendiente*',
            "Ticket *{$vuelta->codigo}*".($sale === null ? '' : " · sale *{$sale}*"),
            '',
            "🚛 *{$vuelta->tracto->placa}*".($vuelta->carreta ? " + carreta {$vuelta->carreta->placa}" : ''),
            $vuelta->conductor ? "👤 {$vuelta->conductor->nombres} {$vuelta->conductor->apellidos}" : null,
            $primera && $ultima ? '📍 '.$this->lugar($primera, true).' → '.$this->lugar($ultima, false) : null,
            '',
            'Dar el apto o no apto:',
            route('taller.index'),
        ], fn (?string $linea): bool => $linea !== null));
    }

    public function revisado(Vuelta $vuelta, RevisionTaller $revision, ?User $autor): void
    {
        if ($revision->resultado === ResultadoTaller::Apto) {
            $this->alertas->avisar(
                TipoAlerta::TallerApto,
                "Ticket {$vuelta->codigo} apto: ya se puede abastecer",
                $this->resumen($vuelta),
                route('abastecimiento.index'),
                $autor,
            );

            return;
        }

        $this->alertas->avisar(
            TipoAlerta::TallerNoApto,
            "Ticket {$vuelta->codigo} NO apto",
            implode("\n", array_filter([
                $this->resumen($vuelta),
                $revision->motivo === null ? null : "Motivo: {$revision->motivo}",
                $revision->disponible_estimado === null ? null : 'Lista para el '.$revision->disponible_estimado->format('d/m H:i'),
            ])),
            route('vueltas.show', $vuelta),
            $autor,
        );
    }

    public function salio(Vuelta $vuelta): void
    {
        $this->alertas->avisar(
            TipoAlerta::TicketSalio,
            "Ticket {$vuelta->codigo} salió con GR",
            $this->resumen($vuelta),
            route('vueltas.show', $vuelta),
        );
    }

    public function cerrado(Vuelta $vuelta, ?User $autor): void
    {
        $this->alertas->avisar(
            TipoAlerta::TicketCerrado,
            "Ticket {$vuelta->codigo} cerrado",
            $this->resumen($vuelta),
            route('vueltas.show', $vuelta),
            $autor,
        );
    }

    /** «VEP-897 · Juan Pérez · sale 04/11 · Juliaca → Lima». */
    private function resumen(Vuelta $vuelta): string
    {
        $vuelta->loadMissing(['tracto:id,placa', 'conductor:id,nombres,apellidos', 'paradas']);
        $primera = $vuelta->paradas->first();
        $ultima = $vuelta->paradas->last();

        return implode(' · ', array_filter([
            $vuelta->tracto->placa,
            $vuelta->conductor ? "{$vuelta->conductor->nombres} {$vuelta->conductor->apellidos}" : null,
            $vuelta->inicio === null ? null : 'sale '.$vuelta->inicio->format('d/m'),
            $primera && $ultima ? $this->lugar($primera, true).' → '.$this->lugar($ultima, false) : null,
        ]));
    }

    private function lugar(ParadaVuelta $parada, bool $origen): string
    {
        return $origen
            ? ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?')
            : ($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?');
    }
}
