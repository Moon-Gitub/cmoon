<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaFoto extends Model
{
    protected $table = 'entrega_fotos';

    protected $fillable = [
        'entrega_id',
        'path',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }
}
