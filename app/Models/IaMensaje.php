<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaMensaje extends Model
{
    protected $table = 'ia_mensajes';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'origen',
        'rol',
        'body',
        'cuenta_cupo',
    ];

    protected function casts(): array
    {
        return [
            'cuenta_cupo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
