<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YcloudConversacion extends Model
{
    protected $table = 'ycloud_conversaciones';

    protected $fillable = [
        'integracion_id',
        'telefono',
        'nombre',
        'handoff_until',
        'last_inbound_at',
    ];

    protected function casts(): array
    {
        return [
            'handoff_until' => 'datetime',
            'last_inbound_at' => 'datetime',
        ];
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(YcloudIntegracion::class, 'integracion_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(YcloudMensaje::class, 'conversacion_id');
    }

    public function enHandoff(): bool
    {
        return $this->handoff_until !== null && $this->handoff_until->isFuture();
    }
}
