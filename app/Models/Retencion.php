<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retencion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'retenciones';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'numero_recibo',
        'user_id',
        'movimiento_cuenta_id',
        'factura_numero',
        'factura_neto',
        'alicuota',
        'monto',
        'monto_neto_pagado',
        'fecha',
        'regimen',
        'jurisdiccion',
        'anulada',
    ];

    protected function casts(): array
    {
        return [
            'factura_neto' => 'decimal:2',
            'alicuota' => 'decimal:3',
            'monto' => 'decimal:2',
            'monto_neto_pagado' => 'decimal:2',
            'fecha' => 'date',
            'anulada' => 'boolean',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientoCuenta(): BelongsTo
    {
        return $this->belongsTo(MovimientoCuenta::class, 'movimiento_cuenta_id');
    }
}
