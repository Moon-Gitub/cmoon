<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('facturas:recurrentes')
    ->dailyAt('07:00')
    ->name('facturas:recurrentes')
    ->withoutOverlapping()
    ->runInBackground();

// Tareas programadas de Tiendanube (solo si está configurado)
if (config('tiendanube.client_id')) {
    Schedule::command('tiendanube:import-abandoned --days=1')
        ->daily()
        ->at('08:00')
        ->name('tiendanube:import-abandoned')
        ->withoutOverlapping()
        ->runInBackground();

    Schedule::command('tiendanube:sync-prices')
        ->hourly()
        ->name('tiendanube:sync-prices')
        ->withoutOverlapping()
        ->runInBackground();
}
