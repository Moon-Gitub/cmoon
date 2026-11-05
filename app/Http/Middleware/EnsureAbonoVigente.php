<?php

namespace App\Http\Middleware;

use App\Services\Moon\MoonBillingClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea solo el registro de ventas cuando el hub Moon Cobros indica bloqueo y
 * el tenant tiene MOON_BILLING_ENFORCE=true. El resto del sistema sigue andando
 * para que el cliente pueda consultar y pagar.
 */
class EnsureAbonoVigente
{
    public function __construct(private MoonBillingClient $hub) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post') || ! $request->routeIs('pos.guardar')) {
            return $next($request);
        }

        $estado = $this->hub->estado();
        if (! $estado || ! $estado['bloquear']) {
            return $next($request);
        }

        $mensaje = ($estado['mensaje'] ?? 'Sistema suspendido por falta de pago.').' Pagá en: '.($estado['pay_url'] ?? 'Moon');

        return $request->expectsJson()
            ? response()->json(['message' => $mensaje, 'pay_url' => $estado['pay_url']], 402)
            : back()->with('error', $mensaje);
    }
}
