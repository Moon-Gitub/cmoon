<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RejectMcpReadOnlyToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $abilities = $token->abilities ?? [];
            $soloMcp = $abilities !== []
                && ! in_array('*', $abilities, true)
                && ! in_array('api', $abilities, true);

            if ($soloMcp) {
                return response()->json(['message' => 'Este token MCP es de solo lectura.'], 403);
            }
        }

        return $next($request);
    }
}
