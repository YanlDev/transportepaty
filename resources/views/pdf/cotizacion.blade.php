{{--
    La proforma con el mismo formato de las que Paty ya hacía a mano (la
    006-0107 a Calcesur): logo, cliente con RUC y dirección, cantidad ×
    precio unitario, IGV desagregado, importe en letras y firma de gerencia.

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
    $descripcion = collect([
        'Servicio de traslado',
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
        @page { margin: 40px 45px 60px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; }
        .azul { color: #17365d; }
        .tenue { color: #6b7280; }
        .rotulo { font-size: 8px; font-weight: bold; letter-spacing: 2px; color: #6b7280; text-transform: uppercase; }

        table.encabezado { width: 100%; border-bottom: 2.5px solid #17365d; padding-bottom: 10px; }
        table.encabezado td { vertical-align: bottom; }
        .logo { height: 52px; }
        .lema { font-size: 7.5px; letter-spacing: .8px; color: #6b7280; margin-top: 4px; }
        .documento { text-align: right; }
        .documento .titulo { font-size: 19px; font-weight: bold; letter-spacing: 5px; color: #17365d; }
        .documento .numero { font-size: 12px; font-weight: bold; color: #2f6db5; margin-top: 4px; }
        .documento .ruc { font-size: 8.5px; color: #6b7280; margin-top: 2px; }

        table.datos { width: 100%; margin-top: 20px; border-collapse: separate; border-spacing: 0; }
        table.datos > tbody > tr > td { vertical-align: top; width: 50%; padding: 12px 14px; }
        .cliente { background: #f1f4f8; }
        table.campos { width: 100%; margin-top: 6px; }
        table.campos td { padding: 2.5px 0; vertical-align: top; }
        table.campos td.etiqueta { width: 78px; color: #6b7280; }
        table.campos td.valor { font-size: 10px; color: #111827; }

        .titulo-detalle { margin-top: 22px; margin-bottom: 5px; }
        table.detalle { width: 100%; border-collapse: collapse; }
        table.detalle th { background: #17365d; color: #fff; font-size: 8.5px; letter-spacing: 1px; padding: 8px 10px; text-align: left; }
        table.detalle th.num { text-align: right; }
        table.detalle th.centro { text-align: center; }
        table.detalle td { padding: 10px; vertical-align: top; font-size: 10px; border-bottom: 1px solid #d1d5db; }
        .num { text-align: right; white-space: nowrap; }
        .centro { text-align: center; }
        .unidad { display: block; font-size: 7.5px; color: #6b7280; }

        table.totales { width: 46%; margin-left: 54%; margin-top: 18px; border-collapse: collapse; }
        table.totales td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        table.totales td.etiqueta { color: #6b7280; }
        table.totales tr.total td { background: #17365d; color: #fff; font-weight: bold; font-size: 12px; letter-spacing: 1px; padding: 9px 10px; border: 0; }

        .letras { margin-top: 18px; font-size: 9px; }
        .letras strong { color: #17365d; }
        .observaciones { margin-top: 14px; font-size: 9px; line-height: 1.5; }
        .observaciones div { margin-top: 2px; }

        .firma { margin-top: 50px; text-align: center; }
        .firma img { height: 72px; }

        .pie { position: fixed; bottom: -35px; left: 0; right: 0; border-top: 1px solid #d1d5db; padding-top: 6px; text-align: center; font-size: 7.5px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="pie">
        Av. Héroes de la Guerra del Pacífico N° 1300 · Juliaca — San Román, Puno · RUC 20364000643
    </div>

    <table class="encabezado">
        <tr>
            <td>
                <img class="logo" src="{{ $imagen('logo-horizontal.png') }}" alt="Empresa de Transportes Paty S.C.R.L.">
                <div class="lema">BRINDA SERVICIO DE TRANSPORTE DE CARGA A NIVEL NACIONAL</div>
            </td>
            <td class="documento">
                <div class="titulo">COTIZACIÓN</div>
                <div class="numero">N° {{ $cotizacion->numero }}</div>
                <div class="ruc">RUC 20364000643</div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td class="cliente">
                <div class="rotulo">Cliente</div>
                <table class="campos">
                    <tr>
                        <td class="etiqueta">Razón social</td>
                        <td class="valor">{{ $cotizacion->cliente_nombre }}</td>
                    </tr>
                    @if ($cotizacion->cliente_ruc)
                        <tr>
                            <td class="etiqueta">RUC</td>
                            <td class="valor">{{ $cotizacion->cliente_ruc }}</td>
                        </tr>
                    @endif
                    @if ($cotizacion->cliente_direccion)
                        <tr>
                            <td class="etiqueta">Dirección</td>
                            <td class="valor">{{ $cotizacion->cliente_direccion }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td>
                <div class="rotulo">Detalles de la cotización</div>
                <table class="campos">
                    <tr>
                        <td class="etiqueta">Fecha</td>
                        <td class="valor">Juliaca, {{ $fecha($cotizacion->fecha) }}</td>
                    </tr>
                    @if ($cotizacion->referencia)
                        <tr>
                            <td class="etiqueta">Referencia</td>
                            <td class="valor">{{ $cotizacion->referencia }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="etiqueta">Validez</td>
                        <td class="valor">{{ $validez }} {{ $validez === 1 ? 'día' : 'días' }} (hasta el {{ $fecha($cotizacion->valido_hasta) }})</td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Moneda</td>
                        <td class="valor">Soles (S/)</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="rotulo titulo-detalle">Detalle del servicio</div>
    <table class="detalle">
        <thead>
            <tr>
                <th style="width: 55px;" class="centro">CANT.</th>
                <th>DESCRIPCIÓN DEL SERVICIO</th>
                <th style="width: 95px;" class="num">P. UNITARIO</th>
                <th style="width: 95px;" class="num">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">
                    {{ $cantidad($cotizacion->cantidad) }}
                    <span class="unidad">{{ $cotizacion->unidad === 'TN' ? 'TN' : ($cotizacion->cantidad == 1 ? 'VIAJE' : 'VIAJES') }}</span>
                </td>
                <td>{{ $descripcion }}</td>
                <td class="num">S/ {{ number_format($cotizacion->precio_unitario, 2) }}</td>
                <td class="num"><strong>S/ {{ number_format($cotizacion->subtotal, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="totales">
        <tr>
            <td class="etiqueta">Subtotal</td>
            <td class="num">S/ {{ number_format($cotizacion->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="etiqueta">IGV ({{ round($cotizacion->subtotal > 0 ? $cotizacion->igv / $cotizacion->subtotal * 100 : 18) }} %)</td>
            <td class="num">S/ {{ number_format($cotizacion->igv, 2) }}</td>
        </tr>
        <tr class="total">
            <td>TOTAL</td>
            <td class="num">S/ {{ number_format($cotizacion->total, 2) }}</td>
        </tr>
    </table>

    <div class="letras">
        <span class="tenue">Importe total en letras:</span>
        <strong>{{ \App\Services\ImporteEnLetras::soles($cotizacion->total) }}</strong>
    </div>

    @if ($cotizacion->km_retorno > 0 || $cotizacion->notas)
        <div class="observaciones">
            <div class="rotulo">Observaciones</div>
            @if ($cotizacion->km_retorno > 0)
                <div>Incluye el retorno de la unidad sin carga.</div>
            @endif
            @if ($cotizacion->notas)
                <div>{!! nl2br(e($cotizacion->notas)) !!}</div>
            @endif
        </div>
    @endif

    <div class="firma">
        <img src="{{ $imagen('firma-gerencia.png') }}" alt="Simona Olga Ramírez de Suxo, Gerente General">
    </div>
</body>
</html>
