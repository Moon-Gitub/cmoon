<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consulta de precio{{ $producto ? ' — '.$producto->nombre : '' }}</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: linear-gradient(160deg, #0f172a, #1e293b 45%, #334155);
            color: #f8fafc;
        }
        .card {
            width: min(92vw, 420px); background: rgba(15, 23, 42, .72);
            border: 1px solid rgba(148, 163, 184, .25); border-radius: 20px;
            padding: 28px 24px; box-shadow: 0 25px 50px rgba(0,0,0,.35);
            backdrop-filter: blur(8px); text-align: center;
        }
        .marca { font-size: 13px; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; margin-bottom: 12px; }
        h1 { font-size: 1.35rem; margin: 0 0 8px; line-height: 1.3; }
        .codigo { color: #94a3b8; font-size: .9rem; margin-bottom: 18px; }
        .precio { font-size: 2.8rem; font-weight: 800; color: #fbbf24; margin: 8px 0 4px; }
        .antes { text-decoration: line-through; color: #94a3b8; font-size: 1.1rem; }
        .badge {
            display: inline-block; margin-top: 12px; padding: 4px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 700; background: #7c3aed; color: #fff;
        }
        .badge.oferta { background: #dc2626; }
        .err { color: #fca5a5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="marca">{{ $empresa?->nombre_fantasia ?: ($empresa?->razon_social ?: 'POSMoon') }}</div>
        @if ($producto)
            <h1>{{ $producto->nombre }}</h1>
            <div class="codigo">{{ $producto->codigo }}</div>
            @if ($producto->promoActiva() && (float) $producto->precio_venta > $producto->precioGondola())
                <div class="antes">$ {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}</div>
            @endif
            <div class="precio">$ {{ number_format($producto->precioGondola(), 2, ',', '.') }}</div>
            @if ($producto->promoActiva())
                <span class="badge oferta">OFERTA</span>
            @endif
            @if ($producto->es_combo)
                <span class="badge">COMBO</span>
            @endif
        @else
            <h1 class="err">Producto no encontrado</h1>
            <p class="codigo">Código: {{ $codigo }}</p>
        @endif
    </div>
</body>
</html>
