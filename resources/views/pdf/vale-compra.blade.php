{{--
    El pedido de compra aprobado, para imprimir o mandar al proveedor.

    Lo que da fe no es este papel sino la página de verificación: el QR (o el
    código escrito a mano) lleva al pedido real en el sistema, con su firma y su
    estado de hoy. Por eso el papel lo dice bien grande.

    El QR va dibujado como una tabla de celdas: dompdf no necesita ninguna
    extensión de imágenes para eso.
--}}
@php
    $monto = fn ($valor): string => $vale->moneda->simbolo().' '.number_format((float) $valor, 2);
    $cantidad = fn ($valor): string => rtrim(rtrim(number_format((float) $valor, 3), '0'), '.');
    $celda = 3;
    $zona = \App\Services\RelojOperativo::ahora()->getTimezone();
    $pagos = $vale->pagos()->vigentes()->with('cuenta')->get();
    $recepciones = $vale->ingresos()->vigentes()->orderBy('fecha')->get();
    $pagado = $vale->pagado();
    $falta = $vale->porPagar();
    $recibido = $vale->recibido();
    $estado = match (true) {
        $falta < 0.01 && $recibido >= (float) $vale->total - 0.01 => 'PAGADO Y RECIBIDO',
        $falta < 0.01 => 'PAGADO · POR RECIBIR',
        $recibido > 0 => 'RECIBIDO · POR PAGAR',
        default => 'APROBADO · POR PAGAR',
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pedido de compra {{ $vale->numero() }}</title>
    <style>
        @page { margin: 36px 44px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #222; line-height: 1.35; }
        p { margin: 0; }
        h1 { font-size: 18px; margin: 0; }
        table { border-collapse: collapse; }
        .ancho { width: 100%; }
        .gris { color: #666; }
        .caja { border: 1px solid #222; padding: 8px 10px; }
        table.lineas th { background: #eee; border: 1px solid #999; padding: 5px 6px; font-size: 9px; text-align: left; }
        table.lineas td { border: 1px solid #999; padding: 5px 6px; }
        .der { text-align: right; }
        .centro { text-align: center; }
        .codigo { font-family: Courier, monospace; font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        table.qr td { width: {{ $celda }}px; height: {{ $celda }}px; padding: 0; line-height: 0; font-size: 0; }
        table.qr td.n { background: #000; }
        .aviso { border: 1px solid #999; background: #f6f6f6; padding: 7px 9px; font-size: 9px; }
        h2 { font-size: 10px; margin: 16px 0 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .vacio { border: 1px dashed #999; padding: 6px 9px; color: #666; }
        table.responsables td { width: 33.33%; border: 1px solid #999; padding: 7px 9px; vertical-align: top; }
        .rotulo { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .nombre { font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>
    <table class="ancho">
        <tr>
            <td style="vertical-align: top;">
                <h1>PEDIDO DE COMPRA</h1>
                <p style="font-size: 11px;">Sustento del gasto · documento interno</p>
                <p style="font-size: 12px; font-weight: bold; margin-top: 4px;">Transportes Paty</p>
                <p class="gris">RUC {{ config('transpaty.facturacion.ruc_empresa') }}</p>
                @if ($vale->tipo === \App\Enums\TipoValeCompra::Emergencia)
                    <p style="margin-top: 6px; font-weight: bold;">COMPRA DE EMERGENCIA EN RUTA</p>
                @endif
            </td>
            <td style="width: 190px; vertical-align: top;">
                <div class="caja centro">
                    <p style="font-size: 16px; font-weight: bold;">{{ $vale->numero() }}</p>
                    <p style="font-weight: bold;">{{ $estado }}</p>
                    <p class="gris">Impreso el {{ now()->timezone($zona)->format('d/m/Y H:i') }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="ancho" style="margin-top: 14px;">
        <tr>
            <td style="vertical-align: top;">
                <p class="gris">Proveedor</p>
                <p style="font-size: 12px; font-weight: bold;">{{ $vale->proveedor->razon_social }}</p>
                @if ($vale->proveedor->ruc)
                    <p>RUC {{ $vale->proveedor->ruc }}</p>
                @endif

                <p class="gris" style="margin-top: 8px;">Para qué es</p>
                <p>{{ $vale->motivo }}</p>
                @if ($vale->vehiculo)
                    <p>Unidad: <strong>{{ $vale->vehiculo->placa }}</strong>@if ($vale->ordenTrabajo) · {{ $vale->ordenTrabajo->numero() }}@endif</p>
                @endif

                @if ($vale->sustento_precio)
                    <p class="gris" style="margin-top: 8px;">Sustento del precio</p>
                    <p>{{ $vale->sustento_precio }}</p>
                @endif
                @if ($vale->getMedia('cotizaciones')->isNotEmpty())
                    <p class="gris">{{ $vale->getMedia('cotizaciones')->count() }} {{ $vale->getMedia('cotizaciones')->count() === 1 ? 'cotización adjunta' : 'cotizaciones adjuntas' }} en el sistema</p>
                @endif
            </td>
            <td style="width: 190px; vertical-align: top;" class="centro">
                <table class="qr" style="margin: 0 auto;">
                    @foreach ($qr as $fila)
                        <tr>@foreach ($fila as $negra)<td @if ($negra) class="n" @endif></td>@endforeach</tr>
                    @endforeach
                </table>
                <p style="margin-top: 5px; font-size: 8px;" class="gris">Escanea para ver su estado de hoy</p>
                <p class="codigo">{{ $vale->codigo_verificacion }}</p>
            </td>
        </tr>
    </table>

    <h2>Lo que se aprobó</h2>
    <table class="ancho lineas">
        <thead>
            <tr>
                <th style="width: 24px;">N.º</th>
                <th>Descripción</th>
                <th class="der" style="width: 60px;">Cantidad</th>
                <th style="width: 40px;">Und.</th>
                <th class="der" style="width: 75px;">Precio</th>
                <th class="der" style="width: 85px;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vale->detalles as $indice => $detalle)
                <tr>
                    <td class="centro">{{ $indice + 1 }}</td>
                    <td>
                        {{ $detalle->descripcion }}
                        @if ($detalle->articulo)
                            <span class="gris">· {{ $detalle->articulo->codigo }}@if ($detalle->articulo->numero_parte) · N/P {{ $detalle->articulo->numero_parte }}@endif</span>
                        @elseif ($detalle->es_servicio)
                            <span class="gris">· servicio</span>
                        @endif
                    </td>
                    <td class="der">{{ $cantidad($detalle->cantidad) }}</td>
                    <td>{{ $detalle->articulo ? ($detalle->articulo->unidad_compra ?? $detalle->articulo->unidad)->abreviatura() : 'serv' }}</td>
                    <td class="der">{{ $monto($detalle->precio_unitario) }}</td>
                    <td class="der">{{ $monto($detalle->total) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5" class="der" style="font-weight: bold;">TOTAL (incluye impuestos)</td>
                <td class="der" style="font-weight: bold; font-size: 12px;">{{ $monto($vale->total) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Es un documento electrónico: nadie lo firma a mano. Quedan los
         nombres de quien pidió y de quien aprobó, con su fecha. --}}
    <h2>Quién lo pidió y quién lo aprobó</h2>
    <table class="ancho responsables">
        <tr>
            <td>
                <p class="rotulo">Pidió la compra</p>
                <p class="nombre">{{ $vale->solicitante?->name ?? '—' }}</p>
                <p class="gris">{{ $vale->created_at?->timezone($zona)->format('d/m/Y H:i') }}</p>
            </td>
            <td>
                <p class="rotulo">Aprobó el gasto</p>
                <p class="nombre">{{ $vale->aprobador?->name ?? '—' }}</p>
                <p class="gris">{{ $vale->aprobado_at?->timezone($zona)->format('d/m/Y H:i') }}</p>
            </td>
            <td>
                <p class="rotulo">Forma de pago</p>
                <p class="nombre">{{ $vale->forma_pago->label() }}{{ $vale->dias_credito ? " a {$vale->dias_credito} días" : '' }}</p>
                <p class="gris">Total {{ $monto($vale->total) }}</p>
            </td>
        </tr>
    </table>

    <h2>Pagos</h2>
    @if ($pagos->isEmpty())
        <p class="vacio">Todavía no hay pagos anotados. Por pagar: {{ $monto($falta) }}.</p>
    @else
        <table class="ancho lineas">
            <thead>
                <tr>
                    <th style="width: 62px;">Fecha</th>
                    <th style="width: 80px;">Medio</th>
                    <th>Operación</th>
                    <th>Cuenta de salida</th>
                    <th class="der" style="width: 85px;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pagos as $pago)
                    <tr>
                        <td>{{ $pago->fecha->format('d/m/Y') }}</td>
                        <td>{{ $pago->medio->label() }}</td>
                        <td>{{ $pago->numero_operacion ?? '—' }}</td>
                        <td>{{ $pago->cuenta ? trim("{$pago->cuenta->banco} {$pago->cuenta->alias}") : '—' }}</td>
                        <td class="der">{{ $monto($pago->monto) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" class="der" style="font-weight: bold;">Pagado · falta {{ $monto($falta) }}</td>
                    <td class="der" style="font-weight: bold;">{{ $monto($pagado) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Recepción en el almacén</h2>
    @if ($recepciones->isEmpty())
        <p class="vacio">La mercadería todavía no entró al almacén.</p>
    @else
        <table class="ancho lineas">
            <thead>
                <tr>
                    <th style="width: 70px;">Recepción</th>
                    <th style="width: 62px;">Fecha</th>
                    <th>Comprobante</th>
                    <th class="der" style="width: 85px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recepciones as $recepcion)
                    <tr>
                        <td>{{ $recepcion->numero() }}</td>
                        <td>{{ $recepcion->fecha->format('d/m/Y') }}</td>
                        <td>{{ $recepcion->comprobante_pendiente ? 'Falta completar el comprobante' : $recepcion->documento().' del '.$recepcion->fecha_emision?->format('d/m/Y') }}</td>
                        <td class="der">{{ $monto($recepcion->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="gris centro" style="margin-top: 14px; font-size: 8px;">Documento interno de Transportes Paty, aprobado en el sistema: no lleva firma ni sello a mano. El QR muestra su estado al día.</p>
    <p class="gris" style="margin-top: 6px; font-size: 7px; word-wrap: break-word;">Huella: {{ $vale->huella }} · {{ $url }}</p>
</body>
</html>
