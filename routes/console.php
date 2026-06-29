<?php

use App\Jobs\Tiendanube\NotifySyncErrors;
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
