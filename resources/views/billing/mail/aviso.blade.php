<!doctype html>
<html lang="es">
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0">
    <tr><td align="center">
        <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;padding:32px">
            <tr><td style="font-size:20px;font-weight:bold;padding-bottom:16px">🌙 POSMoon</td></tr>
            <tr><td style="font-size:15px;line-height:1.6;padding-bottom:24px">{{ $texto }}</td></tr>
            @if ($mostrarBoton)
                <tr><td align="center" style="padding-bottom:24px">
                    <a href="{{ $url }}" style="background:#009ee3;color:#fff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:bold;display:inline-block">Pagar ahora</a>
                </td></tr>
            @endif
            <tr><td style="font-size:12px;color:#64748b">Moon Desarrollos · Este mensaje se envió automáticamente.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
