<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaPago extends Model
{
    protected $table = 'venta_pagos';

    protected $fillable = ['venta_id', 'medio_pago_id', 'importe'];

    protected function casts(): array
    {
        return ['importe' => 'decimal:2'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
    }
}
