<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Los momentos en que el sistema avisa a alguien. Viven en código porque
 * cada uno se dispara desde un punto concreto (taller da el apto, se cierra
 * el ticket…); lo que se ajusta desde la pantalla —si está prendido, por qué
 * canal y a quién— está en `ConfiguracionAlerta`.
 *
 * Los de un ticket salen en el momento; los resúmenes salen una vez al día,
 * a su hora.
 */
enum TipoAlerta: string
{
    use HasLabel;

    case TicketProgramado = 'ticket_programado';
    case TallerApto = 'taller_apto';
    case TallerNoApto = 'taller_no_apto';
    case TicketSalio = 'ticket_salio';
    case TicketCerrado = 'ticket_cerrado';
    case DocumentosPorVencer = 'documentos_por_vencer';
    case FacturasVencidas = 'facturas_vencidas';

    public function label(): string
    {
        return match ($this) {
            self::TicketProgramado => 'Ticket programado',
            self::TallerApto => 'Taller dio apto',
            self::TallerNoApto => 'Taller dio no apto',
            self::TicketSalio => 'El ticket salió (primera GR)',
            self::TicketCerrado => 'Ticket cerrado',
            self::DocumentosPorVencer => 'Documentos vencidos o por vencer',
            self::FacturasVencidas => 'Facturas vencidas',
        };
    }

    /** Cuándo salta, en una frase, para la pantalla de configuración. */
    public function descripcion(): string
    {
        return match ($this) {
            self::TicketProgramado => 'Cuando programación crea un ticket: taller tiene que revisar la unidad.',
            self::TallerApto => 'Cuando taller da el apto: abastecimiento ya puede cargar combustible y viáticos.',
            self::TallerNoApto => 'Cuando taller da el no apto, con el motivo y para cuándo estará lista.',
            self::TicketSalio => 'Cuando llega la primera GR de un ticket programado: la unidad ya salió.',
            self::TicketCerrado => 'Cuando se le pone el candado al ticket: ya se pueden revisar sus gastos.',
            self::DocumentosPorVencer => 'Una vez al día: SOAT, revisiones y licencias vencidas o que vencen pronto.',
            self::FacturasVencidas => 'Una vez al día: las facturas vencidas que siguen sin cobrar.',
        };
    }

    /** Los resúmenes salen una vez al día; los demás, en el momento. */
    public function esResumenDiario(): bool
    {
        return in_array($this, [self::DocumentosPorVencer, self::FacturasVencidas], true);
    }

    /**
     * A qué roles avisa mientras nadie la configure.
     *
     * @return list<string>
     */
    public function rolesPorDefecto(): array
    {
        return match ($this) {
            self::TicketProgramado => ['taller'],
            self::TallerApto => ['abastecimiento'],
            self::TallerNoApto, self::TicketSalio => ['operaciones'],
            self::TicketCerrado, self::FacturasVencidas => ['contador'],
            self::DocumentosPorVencer => ['admin'],
        };
    }

    /** La hora de Lima a la que sale un resumen diario mientras nadie la cambie. */
    public function horaPorDefecto(): ?string
    {
        return match ($this) {
            self::DocumentosPorVencer => '07:30',
            self::FacturasVencidas => '08:00',
            default => null,
        };
    }
}
