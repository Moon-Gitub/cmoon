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

    /** Días de gracia offline cuando el abono está al día */
    'offline_grace_days' => (int) env('DESKTOP_OFFLINE_GRACE_DAYS', 7),

    /** Día del mes a partir del cual se bloquea por mora (como POS legacy) */
    'bloqueo_dia_mes' => (int) env('MOON_BLOQUEO_DIA', 26),
];
