<?php

use App\Http\Controllers\Api\DesktopApiController;
use App\Http\Controllers\Api\MobileFieldApiController;
use App\Http\Middleware\AuthenticateDesktop;
use Illuminate\Support\Facades\Route;

Route::prefix('desktop')->group(function () {
    Route::post('/activate', [DesktopApiController::class, 'activate']);

    Route::middleware(AuthenticateDesktop::class)->group(function () {
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
