<?php

return [
    /*
    | Lista de IPs permitidas (coma en MCP_ALLOWED_IPS). Vacío = cualquier IP
    | autenticada con token mcp:read. Detrás de Cloudflare usar IPs reales
    | (el app ya confía en X-Forwarded-For).
    */
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_ALLOWED_IPS', ''))
    ))),

    /** Días de vida del token emitido por `php artisan mcp:token`. 0 = sin vencimiento. */
    'token_days' => (int) env('MCP_TOKEN_DAYS', 90),
];
