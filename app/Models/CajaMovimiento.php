<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaMovimiento extends Model
{
    protected $table = 'caja_movimientos';

    protected $fillable = ['caja_sesion_id', 'user_id', 'tipo', 'concepto', 'importe'];

    protected function casts(): array
    {
        return ['importe' => 'decimal:2'];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
