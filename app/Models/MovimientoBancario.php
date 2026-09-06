<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoBancario extends Model
{
    protected $table = 'movimientos_bancarios';

    protected $fillable = [
        'cuenta_bancaria_id',
        'fecha',
        'concepto',
        'importe',
        'saldo',
        'referencia_externa',
        'conciliado',
        'conciliado_con_type',
        'conciliado_con_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'importe' => 'decimal:2',
            'saldo' => 'decimal:2',
            'conciliado' => 'boolean',
        ];
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function conciliadoCon(): MorphTo
    {
        return $this->morphTo();
    }
}
