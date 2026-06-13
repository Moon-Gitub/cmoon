<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $comprobante->tipoNombre() }} {{ $comprobante->numeroFormateado() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; padding: 24px; max-width: 800px; margin: 0 auto; }
        .cabecera { display: flex; border: 1px solid #111; position: relative; }
        .col { flex: 1; padding: 12px 16px; }
        .letra { position: absolute; left: 50%; top: 0; transform: translateX(-50%); width: 56px; height: 56px;
                 border: 1px solid #111; border-top: none; background: #fff; display: flex; flex-direction: column;
                 align-items: center; justify-content: center; }
        .letra strong { font-size: 28px; line-height: 1; }
        .letra span { font-size: 8px; }
        h1 { font-size: 16px; margin-bottom: 6px; }
        .derecha { text-align: right; }
        .bloque { border: 1px solid #111; border-top: none; padding: 10px 16px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 0; }
        table.items th { background: #eee; text-align: left; padding: 6px 8px; border: 1px solid #111; font-size: 11px; }
        table.items td { padding: 6px 8px; border: 1px solid #ccc; }
        .num { text-align: right; }
        .totales { margin-top: 0; }
        .totales td { padding: 4px 8px; }
        .totales .total-final { font-size: 16px; font-weight: bold; }
        .pie { display: flex; justify-content: space-between; align-items: flex-end; border: 1px solid #111; border-top: none; padding: 12px 16px; }
        .qr img { width: 110px; height: 110px; }
        @media print { body { padding: 0; } .no-print { display: none; } }
        .no-print { margin-bottom: 16px; text-align: right; }
        .no-print button { padding: 8px 20px; font-size: 13px; cursor: pointer; }
    </style>
</head>
<body onload="if (new URLSearchParams(location.search).get('print')) window.print()">
    <div class="no-print"><button onclick="window.print()">Imprimir</button></div>

    <div class="cabecera">
        <div class="letra">
            <strong>{{ $comprobante->letra() }}</strong>
            <span>COD. {{ str_pad($comprobante->tipo_comprobante, 2, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="col">
            <h1>{{ $comprobante->emisor->razon_social }}</h1>
            <p>{{ $comprobante->emisor->domicilio }}</p>
            <p><strong>{{ $comprobante->emisor->condicion_iva === 'MONOTRIBUTO' ? 'Responsable Monotributo' : 'IVA Responsable Inscripto' }}</strong></p>
        </div>
        <div class="col derecha">
            <h1>{{ strtoupper($comprobante->tipoNombre()) }}</h1>
            <p><strong>N° {{ $comprobante->numeroFormateado() }}</strong></p>
            <p>Fecha de emisión: {{ $comprobante->fecha_emision->format('d/m/Y') }}</p>
            <p>CUIT: {{ $comprobante->emisor->cuit }}</p>
            @if ($comprobante->emisor->ingresos_brutos)<p>IIBB: {{ $comprobante->emisor->ingresos_brutos }}</p>@endif
            @if ($comprobante->emisor->inicio_actividades)<p>Inicio de actividades: {{ $comprobante->emisor->inicio_actividades->format('d/m/Y') }}</p>@endif
        </div>
    </div>

    @if ($comprobante->comprobante_asociado_id && $comprobante->comprobanteAsociado)
        <div class="bloque" style="background: #f5f5f5;">
            <p><strong>Comprobante asociado:</strong>
                {{ $comprobante->comprobanteAsociado->tipoNombre() }} {{ $comprobante->comprobanteAsociado->numeroFormateado() }}
                del {{ $comprobante->comprobanteAsociado->fecha_emision->format('d/m/Y') }}
                @if ($comprobante->concepto) · {{ $comprobante->concepto }} @endif
            </p>
        </div>
    @endif

    <div class="bloque">
        <p><strong>Señor/es:</strong> {{ $comprobante->receptor_nombre }}</p>
        <p><strong>{{ $comprobante->doc_tipo === 80 ? 'CUIT' : ($comprobante->doc_tipo === 96 ? 'DNI' : 'Doc.') }}:</strong>
            {{ $comprobante->doc_numero !== '0' ? $comprobante->doc_numero : 'Consumidor final' }}
            &nbsp;·&nbsp;
            <strong>Condición IVA:</strong> {{ str_replace('_', ' ', $comprobante->receptor_condicion_iva ?? 'CONSUMIDOR FINAL') }}</p>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio unit.</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comprobante->items() as $item)
                <tr>
                    <td>{{ $item['descripcion'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item['cantidad'], 3, ',', '.'), '0'), ',') }}</td>
                    <td class="num">{{ number_format((float) $item['precio_unitario'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $item['total'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="bloque">
        <table class="totales" style="width: 280px; margin-left: auto;">
            @if ($comprobante->letra() !== 'C')
                <tr><td>Neto gravado</td><td class="num">$ {{ number_format((float) $comprobante->neto, 2, ',', '.') }}</td></tr>
                @foreach ($comprobante->detalle_iva ?? [] as $fila)
                    <tr><td>IVA {{ rtrim(rtrim(number_format($fila['alicuota'], 2, ',', ''), '0'), ',') }}%</td>
                        <td class="num">$ {{ number_format($fila['iva'], 2, ',', '.') }}</td></tr>
                @endforeach
            @endif
            <tr class="total-final"><td>TOTAL</td><td class="num">$ {{ number_format((float) $comprobante->total, 2, ',', '.') }}</td></tr>
        </table>
    </div>

    <div class="pie">
        <div class="qr">@if ($qr)<img src="{{ $qr }}" alt="QR AFIP">@endif</div>
        <div class="derecha">
            <p><strong>CAE N°:</strong> {{ $comprobante->cae }}</p>
            <p><strong>Vencimiento CAE:</strong> {{ $comprobante->cae_vencimiento?->format('d/m/Y') }}</p>
            <p style="margin-top: 8px; font-size: 10px;">Comprobante autorizado por AFIP/ARCA</p>
        </div>
    </div>
</body>
</html>
