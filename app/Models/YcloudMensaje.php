<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YcloudMensaje extends Model
{
    protected $table = 'ycloud_mensajes';

    protected $fillable = [
        'integracion_id',
        'conversacion_id',
        'direccion',
        'from_phone',
        'to_phone',
        'wamid',
        'body',
        'respuesta',
        'producto_ids',
        'handoff',
        'status',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'producto_ids' => 'array',
            'handoff' => 'boolean',
            'raw' => 'array',
        ];
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(YcloudIntegracion::class, 'integracion_id');
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(YcloudConversacion::class, 'conversacion_id');
    }
}
