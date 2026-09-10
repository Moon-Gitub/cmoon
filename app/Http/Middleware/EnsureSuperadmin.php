<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->esSuperadmin()) {
            abort(403, 'Solo el supermegaadmin puede acceder a esta sección.');
        }

        return $next($request);
    }
}
