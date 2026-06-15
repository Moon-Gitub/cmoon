<?php

namespace App\Http\Middleware;

use App\Models\DesktopInstallation;
use App\Services\Desktop\DesktopLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDesktop
{
    public function __construct(private DesktopLicenseService $licencias) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken()
            ?? $request->header('X-Desktop-Token');

        if (! $token) {
            return response()->json(['message' => 'Token de dispositivo requerido.'], 401);
        }

        $hash = $this->licencias->hashToken($token);
        $instalacion = DesktopInstallation::with('empresa')
            ->where('token_hash', $hash)
            ->where('activa', true)
            ->first();

        if (! $instalacion) {
            return response()->json(['message' => 'Dispositivo no autorizado.'], 401);
        }

        $instalacion->update(['last_seen_at' => now()]);
        $request->attributes->set('desktop_installation', $instalacion);
        $request->attributes->set('desktop_token', $token);

        // Usuario que activó el dispositivo (preventista/vendedor) o fallback admin
        $userId = $instalacion->user_id
            ?? \App\Models\User::where('empresa_id', $instalacion->empresa_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'Administrador'))
                ->value('id')
            ?? \App\Models\User::where('empresa_id', $instalacion->empresa_id)->value('id');

        if ($userId) {
            auth()->loginUsingId($userId);
        }

        return $next($request);
    }
}
