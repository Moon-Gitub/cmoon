<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoCuenta extends Model
{
    protected $table = 'movimientos_cuenta';

    protected $fillable = [
        'uuid',
        'titular_type',
        'titular_id',
        'tipo',
        'concepto',
        'importe',
        'referencia_type',
        'referencia_id',
        'user_id',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function titular(): MorphTo
    {
        return $this->morphTo();
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
