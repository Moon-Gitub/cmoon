<?php

use App\Jobs\Tiendanube\ImportAbandonedCarts;
use App\Jobs\Tiendanube\NotifySyncErrors;
use App\Jobs\Tiendanube\SyncPromotionalPrices;
use App\Models\TiendanubeIntegracion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notificar errores de sincronización Tiendanube cada 6 horas
Schedule::job(new NotifySyncErrors(hoursBack: 6, minErrorsToNotify: 3))
    ->everySixHours()
    ->name('tiendanube:notify-errors')
    ->withoutOverlapping();

// Importar carritos abandonados diariamente
Schedule::call(function () {
    TiendanubeIntegracion::where('activo', true)
        ->where('import_abandoned', true)
        ->each(fn ($i) => ImportAbandonedCarts::dispatch($i, 1));
})->daily()->at('08:00')->name('tiendanube:import-abandoned');

// Sincronizar precios promocionales cada hora
Schedule::call(function () {
    TiendanubeIntegracion::where('activo', true)
        ->where('sync_prices', true)
        ->each(fn ($i) => SyncPromotionalPrices::dispatch($i));
})->hourly()->name('tiendanube:sync-prices');
