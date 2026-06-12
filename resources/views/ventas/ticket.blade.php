<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #{{ $venta->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 4mm;
            color: #000;
        }
        .centro { text-align: center; }
        .negrita { font-weight: bold; }
        .linea { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .der { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        @media print {
            body { width: auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="centro">
        <p class="negrita" style="font-size:14px">{{ $empresa?->nombre_fantasia ?? $empresa?->razon_social ?? 'CMoon POS' }}</p>
        @if ($empresa?->razon_social && $empresa?->nombre_fantasia)
            <p>{{ $empresa->razon_social }}</p>
        @endif
        @if ($empresa?->cuit)<p>CUIT: {{ $empresa->cuit }}</p>@endif
        @if ($empresa?->domicilio)<p>{{ $empresa->domicilio }}{{ $empresa->localidad ? ', '.$empresa->localidad : '' }}</p>@endif
    </div>

    <div class="linea"></div>

    <p>Comprobante: <span class="negrita">#{{ str_pad($venta->numero, 8, '0', STR_PAD_LEFT) }}</span>
        @if ($venta->estado === 'anulada') <span class="negrita">** ANULADA **</span> @endif
    </p>
    <p>Fecha: {{ $venta->fecha->format('d/m/Y H:i') }}</p>
    <p>Sucursal: {{ $venta->sucursal->nombre }}</p>
    <p>Atendió: {{ $venta->vendedor->name }}</p>
    @if ($venta->cliente)
        <p>Cliente: {{ $venta->cliente->nombre }}{{ $venta->cliente->documento ? ' ('.$venta->cliente->documento.')' : '' }}</p>
    @endif

    <div class="linea"></div>

    <table>
        @foreach ($venta->items as $item)
            <tr>
                <td colspan="2">{{ $item->descripcion }}</td>
            </tr>
            <tr>
                <td>{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', ''), '0'), ',') }} x {{ number_format((float) $item->precio_unitario, 2, ',', '.') }}</td>
                <td class="der">{{ number_format((float) $item->total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linea"></div>

    <table>
        <tr><td>Subtotal</td><td class="der">$ {{ number_format((float) $venta->subtotal, 2, ',', '.') }}</td></tr>
        @if ((float) $venta->descuento > 0)
            <tr><td>Descuento</td><td class="der">- $ {{ number_format((float) $venta->descuento, 2, ',', '.') }}</td></tr>
        @endif
        @if ((float) $venta->recargo > 0)
            <tr><td>Recargo</td><td class="der">$ {{ number_format((float) $venta->recargo, 2, ',', '.') }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="der total">$ {{ number_format((float) $venta->total, 2, ',', '.') }}</td></tr>
    </table>

    <div class="linea"></div>

    <table>
        @foreach ($venta->pagos as $pago)
            <tr>
                <td>{{ $pago->medioPago->nombre }}</td>
                <td class="der">$ {{ number_format((float) $pago->importe, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="linea"></div>

    <p class="centro">¡Gracias por su compra!</p>
    <p class="centro" style="font-size:10px; margin-top:4px">Comprobante no válido como factura</p>

    <div class="no-print centro" style="margin-top:12px">
        <button onclick="window.print()" style="padding:8px 24px; font-size:14px; cursor:pointer">Imprimir</button>
    </div>

    @if (request('print'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
