<?php

namespace App\Services;

use App\Enums\EstadoValeCompra;
use App\Enums\TipoAlerta;
use App\Models\OrdenTrabajo;
use App\Models\RepuestoOrden;
use App\Models\User;
use App\Models\ValeCompra;

/**
 * Los avisos del circuito de órdenes de trabajo, almacén y compras, con su
 * texto: a quién le toca el siguiente paso se entera por la campanita y por
 * WhatsApp, con el enlace que lo lleva directo.
 *
 * El enlace abre la pantalla de siempre: pide iniciar sesión y solo deja
 * actuar a quien tiene el permiso (aprobar una compra es de gerencia).
 */
class AlertasDeTaller
{
    public function __construct(private readonly Alertas $alertas) {}

    public function pedidoPorAprobar(ValeCompra $vale, ?User $autor): void
    {
        $vale->loadMissing(['proveedor', 'detalles', 'ordenTrabajo.vehiculo:id,placa']);

        $this->alertas->avisar(
            TipoAlerta::PedidoPorAprobar,
            "Pedido de compra {$vale->numero()} por aprobar: {$this->monto($vale, (float) $vale->total)}",
            implode("\n", array_filter([
                "Proveedor: {$vale->proveedor->nombre()}",
                'Pago: '.$vale->forma_pago->label().($vale->dias_credito ? " a {$vale->dias_credito} días" : ''),
                $vale->ordenTrabajo === null ? null : "Para la {$vale->ordenTrabajo->numero()} ({$vale->ordenTrabajo->vehiculo->placa})",
                "Motivo: {$vale->motivo}",
                ...$vale->detalles->take(6)->map(fn ($detalle): string => '· '.rtrim(rtrim(number_format((float) $detalle->cantidad, 2), '0'), '.')." × {$detalle->descripcion}")->all(),
                'Abre el enlace para aprobarlo o rechazarlo.',
            ])),
            route('almacen.compras.show', $vale),
            $autor,
        );
    }

    public function pedidoResuelto(ValeCompra $vale, ?User $autor): void
    {
        $aprobado = $vale->estado === EstadoValeCompra::Aprobado;

        $this->alertas->avisar(
            TipoAlerta::PedidoResuelto,
            $aprobado
                ? "Pedido de compra {$vale->numero()} aprobado: ya se puede comprar"
                : "Pedido de compra {$vale->numero()} rechazado",
            $aprobado
                ? "Por pagar: {$this->monto($vale, (float) $vale->total)} ({$vale->forma_pago->label()}). Sirve hasta el {$vale->vence_el?->format('d/m/Y')}."
                : 'Motivo: '.($vale->motivo_rechazo ?? '—'),
            route('almacen.compras.show', $vale),
            $autor,
        );
    }

    public function pagoRegistrado(ValeCompra $vale, float $anotado, ?User $autor): void
    {
        $falta = $vale->porPagar();

        $this->alertas->avisar(
            TipoAlerta::PagoRegistrado,
            "Pago anotado al {$vale->numero()}: {$this->monto($vale, $anotado)}",
            $falta > ValeCompra::CENTIMO
                ? "Falta pagar {$this->monto($vale, $falta)}."
                : 'El pedido quedó pagado: ya se puede recoger la mercadería.',
            route('almacen.compras.show', $vale),
            $autor,
        );
    }

    public function preOrdenAsignada(OrdenTrabajo $orden, ?User $autor): void
    {
        $this->alMecanico($orden, "Tienes una pre-orden: {$this->unidad($orden)}", "{$orden->trabajo}\nRevisa la unidad y arma el requerimiento.", $autor);
    }

    public function requerimientoEnviado(OrdenTrabajo $orden, ?User $autor): void
    {
        $this->alertas->avisar(
            TipoAlerta::RequerimientoPorAprobar,
            "Requerimiento por aprobar: {$this->unidad($orden)}",
            "El mecánico dijo qué va a hacer y qué necesita ({$orden->tareas()->count()} tareas, {$orden->repuestos()->count()} repuestos).",
            route('mantenimiento.ordenes.edit', $orden),
            $autor,
        );
    }

    public function requerimientoAprobado(OrdenTrabajo $orden, ?User $autor): void
    {
        $this->alMecanico($orden, "Orden aprobada: {$this->unidad($orden)}", 'Ya puedes empezar el trabajo.', $autor);

        if ($orden->repuestos()->exists()) {
            $this->alertas->avisar(
                TipoAlerta::OrdenEsperaRepuestos,
                "La {$orden->numero()} espera repuestos ({$orden->vehiculo->placa})",
                $orden->repuestos()->get()->map(fn (RepuestoOrden $repuesto): string => '· '.rtrim(rtrim(number_format((float) $repuesto->cantidad, 2), '0'), '.')." × {$repuesto->descripcion}")->implode("\n"),
                route('almacen.index'),
                $autor,
            );
        }
    }

    public function requerimientoDevuelto(OrdenTrabajo $orden, string $motivo, ?User $autor): void
    {
        $this->alMecanico($orden, "Te devolvieron el requerimiento: {$this->unidad($orden)}", "Hay que cambiar: {$motivo}", $autor);
    }

    public function adicionalPedido(OrdenTrabajo $orden, RepuestoOrden $repuesto, ?User $autor): void
    {
        $this->alertas->avisar(
            TipoAlerta::RequerimientoPorAprobar,
            "Repuesto adicional por aprobar: {$this->unidad($orden)}",
            rtrim(rtrim(number_format((float) $repuesto->cantidad, 2), '0'), '.')." × {$repuesto->descripcion}",
            route('mantenimiento.ordenes.edit', $orden),
            $autor,
        );
    }

    public function adicionalAprobado(OrdenTrabajo $orden, RepuestoOrden $repuesto, ?User $autor): void
    {
        $this->alertas->avisar(
            TipoAlerta::OrdenEsperaRepuestos,
            "Adicional aprobado para la {$orden->numero()} ({$orden->vehiculo->placa})",
            rtrim(rtrim(number_format((float) $repuesto->cantidad, 2), '0'), '.')." × {$repuesto->descripcion}",
            route('almacen.index'),
            $autor,
        );
    }

    public function trabajoTerminado(OrdenTrabajo $orden, ?User $autor): void
    {
        $this->alertas->avisar(
            TipoAlerta::OrdenPorValidar,
            "Orden por validar: {$this->unidad($orden)}",
            'El mecánico terminó el trabajo: revisa las fotos y finaliza u observa.',
            route('mantenimiento.ordenes.edit', $orden),
            $autor,
        );
    }

    public function trabajoObservado(OrdenTrabajo $orden, string $motivo, ?User $autor): void
    {
        $this->alMecanico($orden, "Te observaron el trabajo: {$this->unidad($orden)}", "Hay que corregir: {$motivo}", $autor);
    }

    /**
     * Solo a los mecánicos de la orden, no a todos los mecánicos.
     */
    private function alMecanico(OrdenTrabajo $orden, string $titulo, string $mensaje, ?User $autor): void
    {
        $mecanicos = $orden->mecanicos()->pluck('users.id')->all();

        if ($mecanicos === []) {
            return;
        }

        $this->alertas->avisar(TipoAlerta::OrdenParaElMecanico, $titulo, $mensaje, route('mis-ordenes.show', $orden), $autor, $mecanicos);
    }

    /** «OT-00012 · BUJ-810». */
    private function unidad(OrdenTrabajo $orden): string
    {
        $orden->loadMissing('vehiculo:id,placa');

        return "{$orden->numero()} · {$orden->vehiculo->placa}";
    }

    private function monto(ValeCompra $vale, float $monto): string
    {
        return $vale->moneda->simbolo().' '.number_format($monto, 2);
    }
}
