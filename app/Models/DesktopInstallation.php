<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesktopInstallation extends Model
{
    protected $fillable = [
        'empresa_id',
        'moon_client_id',
        'device_id',
        'device_name',
        'token_hash',
        'last_seen_at',
        'last_sync_at',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
