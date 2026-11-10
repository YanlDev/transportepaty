{{--
    El vale de compra aprobado, para imprimir o mandar al proveedor.

    Lo que da fe no es este papel sino la página de verificación: el QR (o el
    código escrito a mano) lleva al vale real en el sistema, con su firma y su
    estado de hoy. Por eso el papel lo dice bien grande.

    El QR va dibujado como una tabla de celdas: dompdf no necesita ninguna
    extensión de imágenes para eso.
--}}
@php
    $monto = fn ($valor): string => $vale->moneda->simbolo().' '.number_format((float) $valor, 2);
    $cantidad = fn ($valor): string => rtrim(rtrim(number_format((float) $valor, 3), '0'), '.');
    $celda = 3;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Vale de compra {{ $vale->numero() }}</title>
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
        .firmas td { width: 50%; padding-top: 34px; text-align: center; font-size: 9px; }
        .raya { border-top: 1px solid #222; margin: 0 30px 3px; }
    </style>
</head>
<body>
    <table class="ancho">
        <tr>
            <td style="vertical-align: top;">
                <h1>VALE DE COMPRA</h1>
                <p style="font-size: 12px; font-weight: bold;">Transportes Paty</p>
                <p class="gris">RUC {{ config('transpaty.facturacion.ruc_empresa') }}</p>
                @if ($vale->tipo === \App\Enums\TipoValeCompra::Emergencia)
                    <p style="margin-top: 6px; font-weight: bold;">COMPRA DE EMERGENCIA EN RUTA</p>
                @endif
            </td>
            <td style="width: 190px; vertical-align: top;">
                <div class="caja centro">
                    <p style="font-size: 16px; font-weight: bold;">{{ $vale->numero() }}</p>
                    <p>Aprobado el {{ $vale->aprobado_at?->timezone(\App\Services\RelojOperativo::ahora()->getTimezone())->format('d/m/Y H:i') }}</p>
                    <p style="font-weight: bold;">Vale hasta el {{ $vale->vence_el?->format('d/m/Y') }}</p>
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
                <p class="gris" style="margin-top: 8px;">Para</p>
                <p>{{ $vale->motivo }}</p>
                @if ($vale->vehiculo)
                    <p>Unidad: <strong>{{ $vale->vehiculo->placa }}</strong>@if ($vale->ordenTrabajo) · {{ $vale->ordenTrabajo->numero() }}@endif</p>
                @endif
            </td>
            <td style="width: 190px; vertical-align: top;" class="centro">
                <table class="qr" style="margin: 0 auto;">
                    @foreach ($qr as $fila)
                        <tr>@foreach ($fila as $negra)<td @if ($negra) class="n" @endif></td>@endforeach</tr>
                    @endforeach
                </table>
                <p style="margin-top: 5px; font-size: 8px;" class="gris">Código de verificación</p>
                <p class="codigo">{{ $vale->codigo_verificacion }}</p>
            </td>
        </tr>
    </table>

    <table class="ancho lineas" style="margin-top: 14px;">
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
                <td colspan="5" class="der" style="font-weight: bold;">TOTAL A PAGAR (incluye impuestos)</td>
                <td class="der" style="font-weight: bold; font-size: 12px;">{{ $monto($vale->total) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="aviso" style="margin-top: 14px;">
        <p><strong>Antes de entregar, verifique este vale.</strong> Escanee el código QR o entre a <strong>{{ $urlManual }}</strong> y escriba el código de verificación. La página muestra el vale tal como fue aprobado y si todavía sirve: si lo que ve ahí no coincide con este papel —el monto, las cantidades, el proveedor—, o dice que está usado, vencido o anulado, <strong>no entregue</strong>.</p>
        @if ($vale->permite_parciales)
            <p style="margin-top: 4px;">Este vale admite entregas en partes hasta completar su monto, y solo hasta la fecha indicada. La página de verificación dice cuánto queda.</p>
        @else
            <p style="margin-top: 4px;">Este vale sirve para una sola compra, por el monto exacto, y solo hasta la fecha indicada. La factura o boleta debe ser por el mismo importe.</p>
        @endif
    </div>

    <table class="ancho firmas">
        <tr>
            <td>
                <div class="raya"></div>
                <p><strong>{{ $vale->solicitante?->name ?? '—' }}</strong></p>
                <p class="gris">Pidió la compra</p>
            </td>
            <td>
                <div class="raya"></div>
                <p><strong>{{ $vale->aprobador?->name ?? '—' }}</strong></p>
                <p class="gris">Aprobó · firmado digitalmente por el sistema</p>
            </td>
        </tr>
    </table>

    <p class="gris" style="margin-top: 14px; font-size: 7px; word-wrap: break-word;">Huella: {{ $vale->huella }} · {{ $url }}</p>
</body>
</html>
