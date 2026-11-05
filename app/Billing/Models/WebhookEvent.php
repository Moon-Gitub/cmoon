<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $table = 'billing_webhook_events';

    protected $fillable = [
        'proveedor', 'topic', 'resource_id', 'request_id', 'firma_valida', 'payload', 'headers',
        'procesado_at', 'intentos', 'error',
    ];

    protected function casts(): array
    {
        return [
            'firma_valida' => 'boolean',
            'payload' => 'array',
            'headers' => 'array',
            'procesado_at' => 'datetime',
        ];
    }
}
