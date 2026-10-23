<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->activo) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return response()->json(['message' => 'Se requiere un token MCP (Bearer), no sesión web.'], 401);
        }

        if (! in_array('mcp:read', $token->abilities ?? [], true)) {
            return response()->json(['message' => 'El token no tiene permiso mcp:read.'], 403);
        }

        $allowed = config('posmoon_mcp.allowed_ips', []);
        if (is_array($allowed) && $allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            Log::warning('MCP rechazado por IP', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'IP no autorizada para MCP.'], 403);
        }

        $body = json_decode((string) $request->getContent(), true);
        Log::info('MCP', [
            'user_id' => $user->id,
            'empresa_id' => $user->empresa_id,
            'token_id' => $token->id,
            'ip' => $request->ip(),
            'method' => is_array($body) ? ($body['method'] ?? null) : null,
            'tool' => is_array($body) ? data_get($body, 'params.name') : null,
        ]);

        return $next($request);
    }
}
