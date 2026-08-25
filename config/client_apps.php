<?php

return [
    /*
    | Directorios donde se buscan instaladores (en orden). Al publicar una versión
    | nueva, copiá el build con nombre estable o dejá el artefacto versionado acá.
    */
    'directories' => array_values(array_filter([
        env('CLIENT_APPS_DIR'),
        // Volumen persistente en Docker (sobrevive redeploys; útil para AppImage >100MB)
        storage_path('app/descargas'),
        public_path('descargas'),
        base_path('desktop/entregas'),
        base_path('desktop/dist'),
        base_path('mobile/dist'),
    ])),

    'platforms' => [
        'windows' => [
            'label' => 'Windows',
            'description' => 'POSMoon Offline para PC con Windows 10/11 (64 bits).',
            'stable' => 'POSMoon-Offline-Windows.exe',
            'patterns' => [
                'POSMoon-Offline-*-Setup-x64.exe',
                'POSMoon-Offline-Windows.exe',
            ],
        ],
        'linux' => [
            'label' => 'Linux',
            'description' => 'POSMoon Offline para Linux (AppImage o paquete .deb).',
            'stable' => 'POSMoon-Offline-Linux.AppImage',
            'patterns' => [
                'POSMoon-Offline-*-x86_64.AppImage',
                'POSMoon-Offline-*-amd64.deb',
                'POSMoon-Offline-Linux.AppImage',
                'POSMoon-Offline-Linux.deb',
                'CMoon*.AppImage',
                '*POS*-*.AppImage',
            ],
        ],
        'android' => [
            'label' => 'Android',
            'description' => 'App móvil para rutas, escaneo y ventas en el teléfono.',
            'stable' => 'POSMoon-Mobile.apk',
            'patterns' => [
                'POSMoon*.apk',
                'POSMoon-Mobile.apk',
                'CMoon*.apk',
                '*POS*-*.apk',
            ],
        ],
    ],
];
