<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detras de Traefik (Dokploy): confiar en el proxy para esquema/IP reales
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'desktop.auth' => \App\Http\Middleware\AuthenticateDesktop::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
            'mcp.token' => \App\Http\Middleware\EnsureMcpToken::class,
        ]);

        // El POS sincroniza ventas encoladas offline: el token CSRF de la página
        // puede haber rotado para entonces. La ruta sigue exigiendo sesión
        // autenticada y la cookie SameSite=Lax bloquea POST de otros sitios.
        $middleware->validateCsrfTokens(except: [
            'pos/ventas',
            'webhooks/tiendanube',
            'webhooks/shopify',
            'webhooks/ycloud',
            'webhooks/n8n',
            'mcp',
        ]);

        $middleware->appendToGroup('api', \App\Http\Middleware\RejectMcpReadOnlyToken::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('mcp'),
        );

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('mcp')) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }
        });
    })->create();
