<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mercado Pago — cobro con QR en el POS
    |
    | Crear el POS en: Mercado Pago → Tu negocio → Códigos QR → Crear caja
    | El external_pos_id es el identificador de esa caja (store/pos).
    | Documentación: https://www.mercadopago.com.ar/developers/es/docs/qr-code
    |--------------------------------------------------------------------------
    */

    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'user_id' => env('MERCADOPAGO_USER_ID'),
    'external_pos_id' => env('MERCADOPAGO_EXTERNAL_POS_ID'),

];
