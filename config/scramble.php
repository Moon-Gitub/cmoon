<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [

    /*
     * Documentamos solo la API REST v1 (no desktop/móvil).
     */
    'api_path' => [
        'include' => ['api/v1'],
    ],

    'api_domain' => null,

    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'API REST de POSMoon: productos, clientes, ventas e integración Shopify. Autenticación Bearer (Laravel Sanctum).',
    ],

    'ui' => [
        'title' => 'POSMoon API',
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'theme' => 'light',
            'logo' => '',
            'hideModels' => false,
            'hideTryIt' => false,
        ],
    ],

    'servers' => null,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

];
