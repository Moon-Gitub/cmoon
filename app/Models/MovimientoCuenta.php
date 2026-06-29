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
        'factura_numero',
        'factura_neto',
        'factura_iva',
        'medio_pago_id',
        'caja_sesion_id',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'factura_neto' => 'decimal:2',
            'factura_iva' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class);
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
