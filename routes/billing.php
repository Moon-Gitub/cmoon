<?php

use App\Billing\Http\Controllers\Admin\CustomerController;
use App\Billing\Http\Controllers\Admin\DashboardController;
use App\Billing\Http\Controllers\PayPageController;
use App\Billing\Http\Controllers\TenantApiController;
use App\Billing\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| Moon Cobros — solo se carga con BILLING_HUB_ENABLED=true (stack moon-cobros).
*/

Route::post('webhooks/mercadopago', [WebhookController::class, 'mercadopago'])
    ->middleware('throttle:120,1')
    ->name('billing.webhook.mp');

Route::get('api/billing/v1/estado', [TenantApiController::class, 'estado'])
    ->middleware('throttle:60,1')
    ->name('billing.api.estado');

Route::middleware(['web', 'throttle:30,1'])->prefix('pagar/{token}')->group(function () {
    Route::get('/', [PayPageController::class, 'show'])->name('billing.pagar');
    Route::get('/ir', [PayPageController::class, 'ir'])->name('billing.pagar.ir');
    Route::get('/resultado', [PayPageController::class, 'resultado'])->name('billing.pagar.resultado');
    Route::get('/estado', [PayPageController::class, 'estado'])->name('billing.pagar.estado');
});

Route::middleware(['web', 'auth', 'superadmin'])->prefix('cobros')->name('billing.admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pagos', [DashboardController::class, 'pagos'])->name('pagos');
    Route::get('/avisos', [DashboardController::class, 'avisos'])->name('avisos');
    Route::post('/avisos/{customer}', [DashboardController::class, 'marcarAviso'])->name('avisos.marcar');
    Route::get('/eventos', [DashboardController::class, 'eventos'])->name('eventos');
    Route::get('/clientes', [CustomerController::class, 'index'])->name('clientes');
    Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->name('cliente');
    Route::put('/clientes/{customer}', [CustomerController::class, 'update'])->name('cliente.update');
    Route::post('/clientes/{customer}/abono', [CustomerController::class, 'abono'])->name('cliente.abono');
    Route::post('/clientes/{customer}/pago', [CustomerController::class, 'pagoManual'])->name('cliente.pago');
    Route::post('/clientes/{customer}/ajuste', [CustomerController::class, 'ajuste'])->name('cliente.ajuste');
    Route::post('/clientes/{customer}/link', [CustomerController::class, 'regenerarLink'])->name('cliente.link');
    Route::post('/clientes/{customer}/api-key', [CustomerController::class, 'regenerarApiKey'])->name('cliente.apikey');
    Route::post('/clientes/{customer}/whatsapp', [CustomerController::class, 'whatsapp'])->name('cliente.whatsapp');
});
