<?php

use App\Http\Controllers\Api\DesktopApiController;
use App\Http\Controllers\Api\MobileFieldApiController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ClienteApiController;
use App\Http\Controllers\Api\V1\ProductoApiController;
use App\Http\Controllers\Api\V1\ShopifyApiController;
use App\Http\Controllers\Api\V1\VentaApiController;
use App\Http\Middleware\AuthenticateDesktop;
use Illuminate\Support\Facades\Route;

Route::prefix('desktop')->group(function () {
    Route::post('/activate', [DesktopApiController::class, 'activate'])
        ->middleware('throttle:api-auth');

    Route::middleware([AuthenticateDesktop::class, 'throttle:api'])->group(function () {
        Route::get('/license', [DesktopApiController::class, 'license']);
        Route::get('/catalog', [DesktopApiController::class, 'catalog']);
        Route::get('/status', [DesktopApiController::class, 'estado']);
        Route::post('/sync/ventas', [DesktopApiController::class, 'syncVentas']);
        Route::post('/sync/pedidos', [DesktopApiController::class, 'syncPedidos']);

        Route::get('/clientes/{cliente}', [MobileFieldApiController::class, 'cliente'])->whereNumber('cliente');
        Route::get('/rutas/mias', [MobileFieldApiController::class, 'rutasMias']);
        Route::get('/entregas/pendientes', [MobileFieldApiController::class, 'entregasPendientes']);
        Route::get('/reportes/vendedor', [MobileFieldApiController::class, 'reporteVendedor']);
        Route::post('/sync/cobranzas', [MobileFieldApiController::class, 'syncCobranzas']);
        Route::post('/sync/visitas', [MobileFieldApiController::class, 'syncVisitas']);
        Route::post('/sync/entregas', [MobileFieldApiController::class, 'syncEntregas']);
    });
});

/*
|--------------------------------------------------------------------------
| API REST v1 (Sanctum Bearer tokens)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [AuthTokenController::class, 'login'])
        ->middleware('throttle:api-auth');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/auth/logout', [AuthTokenController::class, 'logout']);
        Route::get('/auth/me', [AuthTokenController::class, 'me']);

        Route::middleware('permission:productos.ver')->group(function () {
            Route::get('/productos', [ProductoApiController::class, 'index']);
            Route::get('/productos/{id}', [ProductoApiController::class, 'show'])->whereNumber('id');
        });

        Route::middleware('permission:clientes.ver')->group(function () {
            Route::get('/clientes', [ClienteApiController::class, 'index']);
        });

        Route::middleware('permission:ventas.ver')->group(function () {
            Route::get('/ventas', [VentaApiController::class, 'index']);
            Route::get('/ventas/{id}', [VentaApiController::class, 'show'])->whereNumber('id');
        });

        Route::post('/ventas', [VentaApiController::class, 'store']);

        Route::middleware('permission:empresa.editar')->group(function () {
            Route::get('/shopify/status', [ShopifyApiController::class, 'status']);
            Route::post('/shopify/sync/productos', [ShopifyApiController::class, 'syncProductos']);
        });
    });
});
