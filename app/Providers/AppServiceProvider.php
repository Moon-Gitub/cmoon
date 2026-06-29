<?php

namespace App\Providers;

use App\Events\StockUpdated;
use App\Listeners\SyncStockToTiendanubeOnChange;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Carbon\Carbon::setLocale(config('app.locale', 'es'));
        date_default_timezone_set(config('app.timezone'));

        // Sincronizar stock con Tiendanube cuando cambia (solo si está configurado)
        if (config('tiendanube.client_id')) {
            Event::listen(StockUpdated::class, SyncStockToTiendanubeOnChange::class);
        }
    }
}
