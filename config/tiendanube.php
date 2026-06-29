<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales de la App Tiendanube
    |--------------------------------------------------------------------------
    |
    | Obtener en: https://partners.tiendanube.com/
    | Crear app → obtener client_id y client_secret
    |
    */
    'client_id' => env('TIENDANUBE_CLIENT_ID'),
    'client_secret' => env('TIENDANUBE_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | URLs de la API
    |--------------------------------------------------------------------------
    */
    'api_url' => env('TIENDANUBE_API_URL', 'https://api.tiendanube.com'),
    'api_version' => env('TIENDANUBE_API_VERSION', 'v1'),
    'auth_url' => env('TIENDANUBE_AUTH_URL', 'https://www.tiendanube.com'),

    /*
    |--------------------------------------------------------------------------
    | Scopes (permisos) que solicita la app
    |--------------------------------------------------------------------------
    */
    'scopes' => [
        'read_products',
        'write_products',
        'read_orders',
        'write_orders',
        'read_customers',
        'write_customers',
        'read_coupons',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de sincronización
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'chunk_size' => (int) env('TIENDANUBE_SYNC_CHUNK', 50),
        'rate_limit_per_minute' => (int) env('TIENDANUBE_RATE_LIMIT', 180),
        'retry_attempts' => 3,
        'retry_delay_ms' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo de campos producto POSMoon → Tiendanube
    |--------------------------------------------------------------------------
    */
    'product_mapping' => [
        'name_field' => 'nombre',
        'description_field' => 'descripcion',
        'sku_field' => 'codigo',
        'price_field' => 'precio_venta',
        'cost_field' => 'precio_compra',
        'default_language' => 'es',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'events' => [
            'product/created',
            'product/updated',
            'product/deleted',
            'order/created',
            'order/paid',
            'order/packed',
            'order/fulfilled',
            'order/cancelled',
            'app/uninstalled',
        ],
        'timeout_seconds' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | User-Agent para requests (requerido por Tiendanube)
    |--------------------------------------------------------------------------
    */
    'user_agent' => env('TIENDANUBE_USER_AGENT', 'POSMoon (soporte@posmoon.com)'),

];
