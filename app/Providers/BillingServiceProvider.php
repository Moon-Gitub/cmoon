<?php

namespace App\Providers;

use App\Billing\Console\BillingCommands;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Moon Cobros: rutas, migraciones y tareas programadas solo en el hub
 * (BILLING_HUB_ENABLED=true). Los tenants POS no reciben nada de esto.
 */
class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('billing.hub_enabled')) {
            return;
        }

        $this->loadMigrationsFrom(database_path('migrations/billing'));
        Route::group([], base_path('routes/billing.php'));

        if ($this->app->runningInConsole()) {
            $this->commands(BillingCommands::all());
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $s) {
            $tz = config('billing.timezone');

            if (config('billing.cuotas.generar')) {
                $s->command('billing:generar-cuotas')->monthlyOn(1, '04:00')->timezone($tz)->onOneServer()->withoutOverlapping();
            }
            if (config('billing.legacy.sync_enabled')) {
                $s->command('billing:importar-legacy')->hourly()->withoutOverlapping();
            }
            $s->command('billing:sincronizar-pendientes')->everyFifteenMinutes()->withoutOverlapping();
            $s->command('billing:conciliar --dias=3')->dailyAt('07:00')->timezone($tz)->withoutOverlapping();
            $s->command('billing:avisos')->dailyAt('10:00')->timezone($tz)->withoutOverlapping();
        });
    }
}
