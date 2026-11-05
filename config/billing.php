<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moon Cobros — hub de facturación de abonos (stack Dokploy propio)
    |
    | Solo el stack "moon-cobros" lo activa. Los tenants POS nunca cargan sus
    | rutas ni sus migraciones. Spec: docs/24-moon-cobros-specs.md
    |--------------------------------------------------------------------------
    */

    'hub_enabled' => (bool) env('BILLING_HUB_ENABLED', false),

    'public_url' => rtrim((string) env('BILLING_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),

    'timezone' => env('BILLING_TIMEZONE', 'America/Argentina/Mendoza'),

    'mercadopago' => [
        'access_token' => env('MP_ACCESS_TOKEN'),
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        'collector_id' => env('MP_COLLECTOR_ID'),
        'api_base' => env('MP_API_BASE', 'https://api.mercadopago.com'),
        'timeout' => (int) env('MP_TIMEOUT', 20),
        'webhook_tolerancia_seg' => (int) env('MP_WEBHOOK_TOLERANCIA', 600),
        'aceptar_sandbox' => (bool) env('MP_ACEPTAR_SANDBOX', false),
        'max_cuotas' => (int) env('MP_MAX_CUOTAS', 3),
        'statement_descriptor' => env('MP_STATEMENT_DESCRIPTOR', 'MOON POS'),
        'checkout_ttl_horas' => (int) env('MP_CHECKOUT_TTL_HORAS', 48),
    ],

    'reglas' => [
        'dia_vencimiento' => (int) env('BILLING_DIA_VENCIMIENTO', 10),
        'recargos' => [
            ['desde_dia' => (int) env('BILLING_RECARGO_T1_DIA', 11), 'porcentaje' => (float) env('BILLING_RECARGO_T1_PCT', 10)],
            ['desde_dia' => (int) env('BILLING_RECARGO_T2_DIA', 21), 'porcentaje' => (float) env('BILLING_RECARGO_T2_PCT', 15)],
        ],
        'dia_suspension' => (int) env('BILLING_DIA_SUSPENSION', 26),
        'tolerancia_centavos' => (int) round(((float) env('BILLING_TOLERANCIA', 100)) * 100),
    ],

    'cuotas' => [
        'generar' => (bool) env('BILLING_GENERAR_CUOTAS', false),
        'concepto' => env('BILLING_CONCEPTO_CUOTA', 'Servicio POSMoon'),
    ],

    'avisos' => [
        'enabled' => (bool) env('BILLING_DUNNING_ENABLED', false),
        'canales' => array_filter(explode(',', (string) env('BILLING_DUNNING_CANALES', 'mail,whatsapp'))),
        'cronograma' => [
            1 => 'cuota_emitida',
            8 => 'recordatorio',
            15 => 'vencido',
            22 => 'por_suspender',
        ],
        // WhatsApp exige plantilla aprobada para iniciar conversación.
        // Parámetros del cuerpo: {{1}} nombre, {{2}} total, {{3}} link de pago.
        'whatsapp_templates' => array_filter([
            'cuota_emitida' => env('BILLING_WA_TPL_CUOTA'),
            'recordatorio' => env('BILLING_WA_TPL_RECORDATORIO'),
            'vencido' => env('BILLING_WA_TPL_VENCIDO'),
            'por_suspender' => env('BILLING_WA_TPL_SUSPENSION'),
            'recibo' => env('BILLING_WA_TPL_RECIBO'),
        ]),
        'admin_email' => env('BILLING_ADMIN_EMAIL'),
        'slack_webhook' => env('BILLING_SLACK_WEBHOOK'),
    ],

    'transferencia' => [
        'alias' => env('BILLING_TRANSFER_ALIAS'),
        'cbu' => env('BILLING_TRANSFER_CBU'),
        'titular' => env('BILLING_TRANSFER_TITULAR'),
    ],

    'legacy' => [
        'connection' => 'moon_cobro',
        'sync_enabled' => (bool) env('BILLING_LEGACY_SYNC', false),
        'excluir' => env('BILLING_LEGACY_EXCLUIR', '1,999'),
    ],
];
