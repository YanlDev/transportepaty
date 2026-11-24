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
        $vale->loadMissing(['proveedor', 'detalles', 'solicitante:id,name', 'ordenTrabajo.vehiculo:id,placa']);
        $cotizaciones = $vale->getMedia('cotizaciones');

        $this->alertas->avisar(
            TipoAlerta::PedidoPorAprobar,
            "Pedido de compra {$vale->numero()} por aprobar: {$this->monto($vale, (float) $vale->total)}",
            implode("\n", array_filter([
                "Proveedor: {$vale->proveedor->nombre()}",
                'Pago: '.$this->formaDePago($vale),
                $vale->ordenTrabajo === null ? null : "Para la {$vale->ordenTrabajo->numero()} ({$vale->ordenTrabajo->vehiculo->placa})",
                "Motivo: {$vale->motivo}",
                ...$vale->detalles->take(6)->map(fn ($detalle): string => '· '.$this->cifra((float) $detalle->cantidad)." × {$detalle->descripcion}")->all(),
                'Abre el enlace para aprobarlo o rechazarlo.',
            ])),
            route('almacen.compras.show', $vale),
            $autor,
            whatsapp: $this->lineas([
                '🛒 *Pedido de compra por aprobar*',
                "*{$vale->numero()}* · *{$this->monto($vale, (float) $vale->total)}* · {$this->formaDePago($vale)}",
                '',
                "🏪 {$vale->proveedor->nombre()}",
                $vale->solicitante === null ? null : "👤 Pidió: {$vale->solicitante->name}",
                $vale->ordenTrabajo === null ? null : "🚛 Para la {$vale->ordenTrabajo->numero()} ({$vale->ordenTrabajo->vehiculo->placa})",
                "📝 {$vale->motivo}",
                '',
                ...$this->detalle($vale),
                '',
                match ($cotizaciones->count()) {
                    0 => null,
                    1 => '📎 Va adjunta la cotización.',
                    default => "📎 Va adjunta una cotización; hay {$cotizaciones->count()} en el pedido.",
                },
                'Aprobar o rechazar:',
                route('almacen.compras.show', $vale),
            ]),
            adjunto: $cotizaciones->first()?->id,
        );
    }

    public function pedidoResuelto(ValeCompra $vale, ?User $autor): void
    {
        $vale->loadMissing(['proveedor', 'aprobador:id,name', 'detalles']);
        $aprobado = $vale->estado === EstadoValeCompra::Aprobado;
        $total = $this->monto($vale, (float) $vale->total);

        $this->alertas->avisar(
            TipoAlerta::PedidoResuelto,
            $aprobado
                ? "Pedido de compra {$vale->numero()} aprobado: ya se puede pagar"
                : "Pedido de compra {$vale->numero()} rechazado",
            $aprobado
                ? "Por pagar: {$total} ({$this->formaDePago($vale)})."
                : 'Motivo: '.($vale->motivo_rechazo ?? '—'),
            route('almacen.compras.show', $vale),
            $autor,
            whatsapp: $this->lineas($aprobado ? [
                '✅ *Pedido de compra aprobado*',
                "*{$vale->numero()}* · por pagar *{$total}* · {$this->formaDePago($vale)}",
                '',
                "🏪 {$vale->proveedor->nombre()}",
                blank($vale->proveedor->cuenta_pago) ? null : "🏦 Se le paga a: {$vale->proveedor->cuenta_pago}",
                $vale->aprobador === null ? null : "👤 Aprobó: {$vale->aprobador->name}",
                '',
                'Al pagar, anótalo con su voucher:',
                route('almacen.compras.show', $vale),
            ] : [
                '❌ *Pedido de compra rechazado*',
                "*{$vale->numero()}* · {$total}",
                '',
                "🏪 {$vale->proveedor->nombre()}",
                '📝 Motivo: '.($vale->motivo_rechazo ?? '—'),
                '',
                'Se puede corregir y volver a pedir:',
                route('almacen.compras.show', $vale),
            ]),
        );
    }

    public function pagoRegistrado(ValeCompra $vale, float $anotado, ?User $autor): void
    {
        $vale->loadMissing(['proveedor', 'detalles']);
        $falta = $vale->porPagar();
        $completo = $falta <= ValeCompra::CENTIMO;

        $this->alertas->avisar(
            TipoAlerta::PagoRegistrado,
            "Pago anotado al {$vale->numero()}: {$this->monto($vale, $anotado)}",
            $completo
                ? 'El pedido quedó pagado: ya se puede recibir la mercadería.'
                : "Falta pagar {$this->monto($vale, $falta)}.",
            route('almacen.compras.show', $vale),
            $autor,
            whatsapp: $this->lineas([
                $completo ? '💵 *Pedido pagado: ya se puede recibir*' : '💵 *Pago anotado a un pedido*',
                "*{$vale->numero()}* · pagado {$this->monto($vale, $anotado)}".($completo ? '' : " · falta *{$this->monto($vale, $falta)}*"),
                '',
                "🏪 {$vale->proveedor->nombre()}",
                '',
                ...$this->detalle($vale, conPrecio: false),
                '',
                $completo ? 'Cuando llegue la mercadería, recíbela acá:' : 'Ver el pedido:',
                route('almacen.compras.show', $vale),
            ]),
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

    private function formaDePago(ValeCompra $vale): string
    {
        return $vale->forma_pago->label().($vale->dias_credito ? " a {$vale->dias_credito} días" : '');
    }

    /** «4», «2.5»: la cantidad sin ceros de más. */
    private function cifra(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 2), '0'), '.');
    }

    /**
     * Las primeras líneas del pedido para un mensaje, con su precio si hace
     * falta; si hay más, lo dice.
     *
     * @return list<string>
     */
    private function detalle(ValeCompra $vale, bool $conPrecio = true): array
    {
        $lineas = $vale->detalles->take(8)->map(fn ($detalle): string => '• '.$this->cifra((float) $detalle->cantidad)." × {$detalle->descripcion}"
            .($conPrecio ? ' — '.$this->monto($vale, (float) $detalle->precio_unitario) : ''))->all();

        return $vale->detalles->count() > 8
            ? [...$lineas, '• y '.($vale->detalles->count() - 8).' más']
            : array_values($lineas);
    }

    /**
     * Arma el mensaje: quita lo que no aplica (null) y deja los renglones
     * en blanco, sin dos seguidos.
     *
     * @param  list<string|null>  $lineas
     */
    private function lineas(array $lineas): string
    {
        $texto = implode("\n", array_filter($lineas, fn (?string $linea): bool => $linea !== null));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $texto));
    }
}
