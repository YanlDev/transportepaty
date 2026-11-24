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
    case PedidoPorAprobar = 'pedido_por_aprobar';
    case PedidoResuelto = 'pedido_resuelto';
    case PagoRegistrado = 'pago_registrado';
    case ValeGastoPorAprobar = 'vale_gasto_por_aprobar';
    case ValeGastoResuelto = 'vale_gasto_resuelto';
    case RequerimientoPorAprobar = 'requerimiento_por_aprobar';
    case OrdenPorValidar = 'orden_por_validar';
    case OrdenEsperaRepuestos = 'orden_espera_repuestos';
    case OrdenParaElMecanico = 'orden_para_el_mecanico';

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
            self::PedidoPorAprobar => 'Pedido de compra por aprobar',
            self::PedidoResuelto => 'Pedido de compra aprobado o rechazado',
            self::PagoRegistrado => 'Pago a proveedor anotado',
            self::ValeGastoPorAprobar => 'Vale de gasto por aprobar',
            self::ValeGastoResuelto => 'Vale de gasto aprobado o rechazado',
            self::RequerimientoPorAprobar => 'Requerimiento del mecánico por aprobar',
            self::OrdenPorValidar => 'Orden de trabajo por validar',
            self::OrdenEsperaRepuestos => 'Orden aprobada que espera repuestos',
            self::OrdenParaElMecanico => 'Novedad en una orden del mecánico',
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
            self::PedidoPorAprobar => 'Cuando alguien pide una compra (o la corrige): gerencia la revisa y la aprueba desde el enlace.',
            self::PedidoResuelto => 'Cuando gerencia aprueba o rechaza un pedido: quien compra ya sabe si puede comprar, y contabilidad que hay un pago pendiente.',
            self::PagoRegistrado => 'Cuando se anota el pago de una compra: ya se puede recoger la mercadería.',
            self::ValeGastoPorAprobar => 'Cuando un área pide un vale de gasto: gerencia lo revisa y lo aprueba desde el enlace.',
            self::ValeGastoResuelto => 'A quien pidió el vale de gasto, cuando gerencia lo aprueba o lo rechaza.',
            self::RequerimientoPorAprobar => 'Cuando el mecánico envía su requerimiento (tareas, repuestos y fotos) o pide un repuesto adicional.',
            self::OrdenPorValidar => 'Cuando el mecánico termina el trabajo: falta revisarlo y finalizar la orden.',
            self::OrdenEsperaRepuestos => 'Cuando se aprueba un requerimiento (o un adicional) con repuestos: el almacén tiene que entregarlos o comprarlos.',
            self::OrdenParaElMecanico => 'Al mecánico de la orden, cuando le asignan una pre-orden, le aprueban o devuelven el requerimiento, o le observan el trabajo.',
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
            self::PedidoPorAprobar, self::ValeGastoPorAprobar => ['gerencia'],
            self::ValeGastoResuelto => ['operaciones', 'taller', 'abastecimiento', 'contador', 'rrhh', 'almacen'],
            self::PedidoResuelto => ['almacen', 'taller', 'contador'],
            self::PagoRegistrado => ['almacen', 'taller'],
            self::RequerimientoPorAprobar => ['operaciones'],
            self::OrdenPorValidar => ['operaciones'],
            self::OrdenEsperaRepuestos => ['almacen', 'taller'],
            self::OrdenParaElMecanico => ['mecanico'],
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
