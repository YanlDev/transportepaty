{{--
    La proforma tal como el cliente ya la conoce en papel: mismo encabezado,
    mismo orden de columnas y el IGV desagregado al pie. El desglose interno de
    costos (fijos, variables, margen) no va acá a propósito —al cliente se le
    cotiza un monto por el servicio, no la estructura de costos de la casa.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Proforma {{ $cotizacion->numero }}</title>
    <style>
        @page { margin: 30px 35px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        .encabezado { width: 100%; margin-bottom: 25px; }
        .encabezado td { vertical-align: top; }
        .empresa { font-size: 12px; font-weight: bold; line-height: 1.35; }
        .empresa small { display: block; font-size: 9px; font-weight: normal; }
        .documento { text-align: right; line-height: 1.5; }
        .documento .ruc { font-weight: bold; }
        .documento .titulo { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .destinatario { margin-bottom: 18px; line-height: 1.6; }
        .destinatario strong { display: inline-block; width: 75px; }
        table.detalle { width: 100%; border-collapse: collapse; }
        table.detalle th, table.detalle td { border: 1px solid #444; padding: 6px 8px; }
        table.detalle th { background: #eee; font-size: 9px; letter-spacing: .5px; }
        .num { text-align: right; white-space: nowrap; }
        .centro { text-align: center; }
        table.totales { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        table.totales td { padding: 4px 8px; }
        table.totales .etiqueta { text-align: right; font-weight: bold; }
        table.totales .total { border-top: 1px solid #444; font-size: 12px; font-weight: bold; }
        .condiciones { margin-top: 25px; font-size: 9px; line-height: 1.6; }
        .condiciones h4 { margin: 0 0 4px; font-size: 9px; letter-spacing: .5px; }
        .firma { margin-top: 55px; text-align: center; line-height: 1.5; }
        .firma .linea { border-top: 1px solid #444; width: 260px; margin: 0 auto 6px; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="empresa">
                EMPRESA DE TRANSPORTES PATY
                <small>SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA</small>
                <small>BRINDA SERVICIO DE TRANSPORTES DE CARGA A NIVEL NACIONAL</small>
            </td>
            <td class="documento">
                <div class="ruc">RUC N° 20364000643</div>
                <div class="titulo">PROFORMA</div>
                <div>N° {{ $cotizacion->numero }}</div>
                <div>JULIACA, {{ mb_strtoupper($cotizacion->fecha->translatedFormat('d \d\e F \d\e\l Y')) }}</div>
            </td>
        </tr>
    </table>

    <div class="destinatario">
        <div><strong>SEÑORES:</strong> {{ $cotizacion->cliente_nombre }}</div>
        @if ($cotizacion->cliente_ruc)
            <div><strong>RUC:</strong> {{ $cotizacion->cliente_ruc }}</div>
        @endif
    </div>

    <table class="detalle">
        <thead>
            <tr>
                <th style="width: 45px;">CANT.</th>
                <th>DESCRIPCIÓN</th>
                <th style="width: 95px;">P. UNITARIO</th>
                <th style="width: 95px;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">01</td>
                {{-- En una sola cadena y no con @if intercalados: dompdf
                     separa cada trozo en su propio nodo y deja un espacio
                     antes de la coma. --}}
                <td>{{ mb_strtoupper(collect([
                    'SERVICIO DE TRANSPORTE DE CARGA',
                    $cotizacion->material ? "DE {$cotizacion->material}" : null,
                ])->filter()->implode(' ').", DESDE {$cotizacion->origen}, CON DESTINO A {$cotizacion->destino}.") }}</td>
                <td class="num">S/. {{ number_format($cotizacion->subtotal, 2) }}</td>
                <td class="num">S/. {{ number_format($cotizacion->subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totales">
        <tr>
            <td class="etiqueta">SUBTOTAL</td>
            <td class="num">S/. {{ number_format($cotizacion->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="etiqueta">IGV (18%)</td>
            <td class="num">S/. {{ number_format($cotizacion->igv, 2) }}</td>
        </tr>
        <tr>
            <td class="etiqueta total">TOTAL</td>
            <td class="num total">S/. {{ number_format($cotizacion->total, 2) }}</td>
        </tr>
    </table>

    <div class="condiciones">
        <h4>CONDICIONES</h4>
        <div>VÁLIDO HASTA EL {{ mb_strtoupper($cotizacion->valido_hasta->translatedFormat('d \d\e F \d\e\l Y')) }}.</div>
        <div>
            El monto corresponde al servicio de transporte de carga en la ruta
            {{ $cotizacion->origen }} - {{ $cotizacion->destino }} ({{ number_format($cotizacion->km) }} km).
        </div>
        @if ($cotizacion->notas)
            <div>{{ $cotizacion->notas }}</div>
        @endif
    </div>

    <div class="firma">
        <div class="linea"></div>
        <div><strong>EMPRESA DE TRANSPORTES PATY S.C.R.L.</strong></div>
        <div>Simona Olga Ramírez de Suxo</div>
        <div>Representante Legal</div>
    </div>
</body>
</html>
