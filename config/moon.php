<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base de datos Moon — sistema de cobros (POS Moon clientes)
    |--------------------------------------------------------------------------
    */
    'cobro' => [
        'enabled' => env('MOON_COBRO_ENABLED', false),
        'host' => env('MOON_DB_HOST', '127.0.0.1'),
        'port' => env('MOON_DB_PORT', '3306'),
        'database' => env('MOON_DB_DATABASE', ''),
        'username' => env('MOON_DB_USERNAME', ''),
        'password' => env('MOON_DB_PASSWORD', ''),
    ],

    /*
    | Hub Moon Cobros (stack moon-cobros). Si url+key están, reemplaza la
    | lectura directa de la base legacy. Bloquear exige además enforce=true.
    */
    'billing' => [
        'url' => rtrim((string) env('MOON_BILLING_URL', ''), '/'),
        'key' => env('MOON_BILLING_KEY', ''),
        'enforce' => (bool) env('MOON_BILLING_ENFORCE', false),
        'cache_min' => (int) env('MOON_BILLING_CACHE_MIN', 10),
        'fallback_horas' => (int) env('MOON_BILLING_FALLBACK_HORAS', 72),
    ],

    /** Días de gracia offline cuando el abono está al día */
    'offline_grace_days' => (int) env('DESKTOP_OFFLINE_GRACE_DAYS', 7),

    /** Día del mes a partir del cual se bloquea por mora (como POS legacy) */
    'bloqueo_dia_mes' => (int) env('MOON_BLOQUEO_DIA', 26),
];
