<?php

namespace App\Providers;

use App\Events\StockUpdated;
use App\Listeners\SyncStockToTiendanubeOnChange;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        \App\Models\Producto::observe(\App\Observers\ProductoObserver::class);
        \App\Models\Venta::observe(\App\Observers\VentaObserver::class);

        // Sincronizar stock con Tiendanube cuando cambia (solo si está configurado)
        if (config('tiendanube.client_id')) {
            Event::listen(StockUpdated::class, SyncStockToTiendanubeOnChange::class);
        }

        $this->configureScramble();
    }

    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        Gate::define('viewApiDocs', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }

            return $user && (
                $user->can('empresa.editar')
                || $user->hasRole('admin')
                || $user->hasRole('Admin')
            );
        });

        Scramble::routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/v1');
        });
    }
}
