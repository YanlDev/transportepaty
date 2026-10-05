{{--
    La proforma armada como las cartas comerciales que se mandan en el rubro:
    membrete con los datos de la empresa, el recuadro del RUC y el número (el
    mismo de una factura), «Señores / Presente», la tabla con bordes con el
    «SON:» y los totales adentro, las condiciones de la oferta y la firma de
    gerencia bajo «Atentamente».

    El desglose interno (costos fijos y variables, margen, tarifa calculada y
    rebaja) no va acá a propósito: al cliente se le cotiza un precio por el
    servicio, no la estructura de costos de la casa.

    Las imágenes van embebidas en base64 y no por ruta: dompdf no lee archivos
    fuera de su chroot y así el PDF no depende de dónde esté instalado.
--}}
@php
    $imagen = fn (string $archivo): string => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path("marca/{$archivo}")));
    $fecha = fn ($dia): string => $dia->translatedFormat('j \d\e F \d\e Y');
    $cantidad = fn (float $valor): string => fmod($valor, 1.0) === 0.0 ? number_format($valor) : number_format($valor, 2);
    $validez = (int) $cotizacion->fecha->diffInDays($cotizacion->valido_hasta);
    $telefono = \App\Models\Ajuste::valor(\App\Models\Ajuste::TELEFONO_OFICINA);
    $tasaIgv = round($cotizacion->subtotal > 0 ? $cotizacion->igv / $cotizacion->subtotal * 100 : 18);
    $unidad = $cotizacion->unidad === 'TN' ? 'TN' : ($cotizacion->cantidad == 1 ? 'VIAJE' : 'VIAJES');
    $descripcion = collect([
        'Servicio de transporte',
        $cotizacion->material ? "de {$cotizacion->material}" : 'de carga',
        "desde {$cotizacion->origen}",
        "con destino a {$cotizacion->destino}",
    ])->implode(' ');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->numero }}</title>
    <style>
        @page { margin: 36px 48px 64px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #222; line-height: 1.35; }
        p { margin: 0; }

        table.membrete { width: 100%; border-collapse: collapse; }
        table.membrete td { vertical-align: top; }
        .logo { height: 50px; }
        .empresa { margin-top: 6px; font-size: 8.5px; color: #444; line-height: 1.45; }
        .empresa strong { font-size: 9px; color: #222; }

        .recuadro { width: 190px; border: 1.5px solid #17365d; text-align: center; }
        .recuadro div { padding: 7px 4px; }
        .recuadro .ruc { font-size: 11px; font-weight: bold; }
        .recuadro .tipo { background: #17365d; color: #fff; font-size: 12px; font-weight: bold; }
        .recuadro .numero { font-size: 12px; font-weight: bold; }

        .lugar-fecha { margin-top: 22px; text-align: right; }

        .destinatario { margin-top: 14px; }
        .destinatario .cliente { font-weight: bold; font-size: 10.5px; }
        .destinatario .presente { margin-top: 4px; font-weight: bold; text-decoration: underline; }
        .referencia { margin-top: 10px; }

        .saludo { margin-top: 14px; }
        .saludo p + p { margin-top: 6px; }

        table.detalle { width: 100%; margin-top: 12px; border-collapse: collapse; }
        table.detalle th, table.detalle td { border: 0.75px solid #555; padding: 6px 7px; vertical-align: top; }
        table.detalle th { background: #e4e9f0; font-size: 8.5px; font-weight: bold; text-align: center; }
        table.detalle td.descripcion { height: 70px; }
        table.detalle td.son { vertical-align: middle; font-size: 9px; }
        table.detalle td.concepto { font-size: 9px; font-weight: bold; }
        table.detalle tr.total td.concepto,
        table.detalle tr.total td.num { font-size: 10.5px; }
        .num { text-align: right; white-space: nowrap; }
        .centro { text-align: center; }

        .condiciones { margin-top: 16px; }
        .condiciones .titulo { font-weight: bold; text-decoration: underline; }
        .condiciones ul { margin: 4px 0 0; padding-left: 14px; }
        .condiciones li { margin-top: 2px; }

        .despedida { margin-top: 16px; }
        .firma { margin-top: 4px; text-align: center; }
        .firma img { height: 78px; }

        .pie { position: fixed; bottom: -40px; left: 0; right: 0; border-top: 0.75px solid #999; padding-top: 5px; text-align: center; font-size: 7.5px; color: #555; }
    </style>
</head>
<body>
    <div class="pie">
        Empresa de Transportes Paty S.C.R.L. · Av. Héroes de la Guerra del Pacífico N° 1300, Juliaca — San Román, Puno
        @if ($telefono)
            · Telf. {{ $telefono }}
        @endif
    </div>

    <table class="membrete">
        <tr>
            <td>
                <img class="logo" src="{{ $imagen('logo-horizontal.png') }}" alt="Empresa de Transportes Paty S.C.R.L.">
                <div class="empresa">
                    <strong>EMPRESA DE TRANSPORTES PATY S.C.R.L.</strong><br>
                    Transporte de carga a nivel nacional<br>
                    Av. Héroes de la Guerra del Pacífico N° 1300 — Juliaca, Puno
                    @if ($telefono)
                        <br>Telf. {{ $telefono }}
                    @endif
                </div>
            </td>
            <td style="width: 190px;">
                <div class="recuadro">
                    <div class="ruc">R.U.C. N° 20364000643</div>
                    <div class="tipo">COTIZACIÓN</div>
                    <div class="numero">N° {{ $cotizacion->numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    <p class="lugar-fecha">Juliaca, {{ $fecha($cotizacion->fecha) }}</p>

    <div class="destinatario">
        <p>Señores:</p>
        <p class="cliente">{{ $cotizacion->cliente_nombre }}</p>
        @if ($cotizacion->cliente_ruc)
            <p>RUC: {{ $cotizacion->cliente_ruc }}</p>
        @endif
        @if ($cotizacion->cliente_direccion)
            <p>{{ $cotizacion->cliente_direccion }}</p>
        @endif
        <p class="presente">Presente.-</p>
    </div>

    @if ($cotizacion->referencia)
        <p class="referencia"><strong>Ref.:</strong> {{ $cotizacion->referencia }}</p>
    @endif

    <div class="saludo">
        <p>De nuestra consideración:</p>
        <p>
            Por medio de la presente le saludamos cordialmente y, atendiendo a su solicitud,
            le hacemos llegar nuestra propuesta económica por el servicio de transporte de carga
            que se detalla a continuación:
        </p>
    </div>

    <table class="detalle">
        <thead>
            <tr>
                <th style="width: 42px;">CANT.</th>
                <th style="width: 48px;">UNIDAD</th>
                <th>DESCRIPCIÓN</th>
                <th style="width: 78px;">P. UNIT. S/</th>
                <th style="width: 82px;">IMPORTE S/</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">{{ $cantidad($cotizacion->cantidad) }}</td>
                <td class="centro">{{ $unidad }}</td>
                <td class="descripcion">{{ $descripcion }}</td>
                <td class="num">{{ number_format($cotizacion->precio_unitario, 2) }}</td>
                <td class="num">{{ number_format($cotizacion->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="son" colspan="3" rowspan="3">
                    <strong>SON:</strong> {{ \App\Services\ImporteEnLetras::soles($cotizacion->total) }}
                </td>
                <td class="concepto">SUBTOTAL</td>
                <td class="num">{{ number_format($cotizacion->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="concepto">I.G.V. {{ $tasaIgv }}%</td>
                <td class="num">{{ number_format($cotizacion->igv, 2) }}</td>
            </tr>
            <tr class="total">
                <td class="concepto">TOTAL S/</td>
                <td class="num"><strong>{{ number_format($cotizacion->total, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="condiciones">
        <p class="titulo">Condiciones de la oferta</p>
        <ul>
            <li>Precios expresados en soles; el I.G.V. se muestra por separado.</li>
            <li>Validez de la oferta: {{ $validez }} {{ $validez === 1 ? 'día' : 'días' }}, hasta el {{ $fecha($cotizacion->valido_hasta) }}.</li>
            @if ($cotizacion->km_retorno > 0)
                <li>Incluye el retorno de la unidad sin carga.</li>
            @endif
            @if ($cotizacion->notas)
                <li>{!! nl2br(e($cotizacion->notas)) !!}</li>
            @endif
        </ul>
    </div>

    <div class="despedida">
        <p>Sin otro particular, y a la espera de su conformidad, quedamos de ustedes.</p>
        <p style="margin-top: 10px;">Atentamente,</p>
    </div>

    <div class="firma">
        <img src="{{ $imagen('firma-gerencia.png') }}" alt="Simona Olga Ramírez de Suxo, Gerente General">
    </div>
</body>
</html>
