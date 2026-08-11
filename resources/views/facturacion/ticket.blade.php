<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $comprobante->tipoNombre() }} {{ $comprobante->numeroFormateado() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            width: 80mm;
            margin: 0 auto;
            padding: 3mm;
            color: #000;
        }
        .centro { text-align: center; }
        .negrita { font-weight: bold; }
        .linea { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .der { text-align: right; }
        .total { font-size: 14px; font-weight: bold; }
        .letra { font-size: 18px; font-weight: bold; }
        @media print {
            body { width: auto; }
            .no-print { display: none; }
        }
        .no-print { margin-bottom: 8px; text-align: center; }
        .no-print button { padding: 6px 14px; font-size: 12px; }
    </style>
</head>
<body onload="if (new URLSearchParams(location.search).get('print')) window.print()">
    <div class="no-print"><button onclick="window.print()">Imprimir ticket</button></div>

    <div class="centro">
        <p class="letra">{{ $comprobante->letra() }}</p>
        <p class="negrita">{{ $comprobante->emisor->razon_social }}</p>
        <p>CUIT: {{ $comprobante->emisor->cuit }}</p>
        @if ($comprobante->emisor->domicilio)<p>{{ $comprobante->emisor->domicilio }}</p>@endif
        <p>{{ $comprobante->emisor->condicion_iva === 'MONOTRIBUTO' ? 'Resp. Monotributo' : 'IVA RI' }}</p>
    </div>

    <div class="linea"></div>

    <p class="negrita centro">{{ strtoupper($comprobante->tipoNombre()) }}</p>
    <p class="centro">N° {{ $comprobante->numeroFormateado() }}</p>
    <p class="centro">{{ $comprobante->fecha_emision->format('d/m/Y') }}</p>

    <div class="linea"></div>

    <p><strong>{{ $comprobante->receptor_nombre }}</strong></p>
    <p>{{ $comprobante->doc_tipo === 80 ? 'CUIT' : ($comprobante->doc_tipo === 96 ? 'DNI' : 'Doc.') }}:
        {{ $comprobante->doc_numero !== '0' ? $comprobante->doc_numero : 'Consumidor final' }}</p>

    <div class="linea"></div>

    <table>
        @foreach ($comprobante->items() as $item)
            <tr>
                <td colspan="2">{{ $item['descripcion'] }}</td>
            </tr>
            <tr>
                <td>{{ rtrim(rtrim(number_format((float) $item['cantidad'], 3, ',', '.'), '0'), ',') }}
                    x {{ number_format((float) $item['precio_unitario'], 2, ',', '.') }}</td>
                <td class="der">{{ number_format((float) $item['total'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linea"></div>

    @if ($comprobante->letra() === 'A')
        <table>
            <tr><td>Neto</td><td class="der">$ {{ number_format((float) $comprobante->neto, 2, ',', '.') }}</td></tr>
            @foreach ($comprobante->detalle_iva ?? [] as $fila)
                <tr>
                    <td>IVA {{ rtrim(rtrim(number_format($fila['alicuota'], 2, ',', ''), '0'), ',') }}%</td>
                    <td class="der">$ {{ number_format($fila['iva'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="total centro">TOTAL $ {{ number_format((float) $comprobante->total, 2, ',', '.') }}</p>

    @if ($comprobante->letra() === 'B' && (float) $comprobante->iva > 0)
        <div class="linea"></div>
        <p style="font-size:9px">Régimen de Transparencia Fiscal al Consumidor Ley 27.743</p>
        <p>IVA: $ {{ number_format((float) $comprobante->iva, 2, ',', '.') }}</p>
    @endif

    <div class="linea"></div>

    @if ($qr)
        <div class="centro">{!! $qr !!}</div>
    @endif
    <p class="centro">CAE: {{ $comprobante->cae }}</p>
    <p class="centro">Vto CAE: {{ $comprobante->cae_vencimiento?->format('d/m/Y') }}</p>
    <p class="centro" style="font-size:9px;margin-top:4px">Autorizado por AFIP/ARCA</p>
</body>
</html>
