<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActividad extends Model
{
    protected $table = 'crm_actividades';

    protected $fillable = [
        'oportunidad_id',
        'user_id',
        'tipo',
        'titulo',
        'cuerpo',
        'hecha_at',
    ];

    protected function casts(): array
    {
        return [
            'hecha_at' => 'datetime',
        ];
    }

    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(CrmOportunidad::class, 'oportunidad_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
