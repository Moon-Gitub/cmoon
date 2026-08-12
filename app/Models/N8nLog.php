<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class N8nLog extends Model
{
    protected $table = 'n8n_logs';

    protected $fillable = [
        'integracion_id',
        'evento',
        'direccion',
        'url',
        'http_status',
        'payload',
        'status',
        'mensaje',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(N8nIntegracion::class, 'integracion_id');
    }
}
