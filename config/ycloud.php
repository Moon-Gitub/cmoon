<?php

return [

    'api_base' => env('YCLOUD_API_BASE', 'https://api.ycloud.com/v2'),

    'api_key' => env('YCLOUD_API_KEY'),

    'phone_from' => env('YCLOUD_PHONE_FROM'),

    'waba_id' => env('YCLOUD_WABA_ID'),

    'webhook_secret' => env('YCLOUD_WEBHOOK_SECRET'),

    'catalog_template' => env('YCLOUD_CATALOG_TEMPLATE'),

    /*
    | OpenAI-compatible (OpenAI, Groq, etc.) para responder consultas.
    | Si no hay API key, el bot usa búsqueda por nombre/código.
    */
    'openai_api_key' => env('OPENAI_API_KEY'),

    'openai_base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

];
