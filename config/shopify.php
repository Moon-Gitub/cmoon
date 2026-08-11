<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales globales de la App Shopify (Partner / Custom App)
    |--------------------------------------------------------------------------
    |
    | Obtener en: https://partners.shopify.com/ → Apps
    | O en Admin → Settings → Apps → Develop apps (custom app + Admin API token).
    |
    | Las credenciales por empresa se guardan en shopify_integraciones.
    | Estos valores sirven de default / HMAC de webhooks a nivel app.
    |
    */
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Versión Admin API
    |--------------------------------------------------------------------------
    |
    | Formato YYYY-MM. Ver: https://shopify.dev/docs/api/admin-rest
    |
    */
    'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),

    /*
    |--------------------------------------------------------------------------
    | Scopes recomendados (documentación / OAuth futuro)
    |--------------------------------------------------------------------------
    */
    'scopes' => [
        'read_products',
        'write_products',
        'read_orders',
        'read_inventory',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronización
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'chunk_size' => (int) env('SHOPIFY_SYNC_CHUNK', 50),
        'retry_attempts' => 3,
        'retry_delay_ms' => 1000,
        'max_pages' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo producto Shopify ↔ POSMoon
    |--------------------------------------------------------------------------
    */
    'product_mapping' => [
        'sku_field' => 'codigo',
        'name_field' => 'nombre',
        'description_field' => 'descripcion',
        'price_field' => 'precio_venta',
        'cost_field' => 'precio_compra',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'topics' => [
            'orders/create',
            'orders/paid',
            'products/create',
            'products/update',
            'app/uninstalled',
        ],
    ],

];
