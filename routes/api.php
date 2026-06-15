<?php

use App\Http\Controllers\Api\DesktopApiController;
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
    });
});
