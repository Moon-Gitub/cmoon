<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'billing_payments';

    protected $fillable = [
        'customer_id', 'proveedor', 'external_id', 'estado', 'estado_detalle', 'importe_centavos',
        'neto_centavos', 'metodo', 'tipo_pago', 'checkout_id', 'aprobado_at', 'acreditado_at', 'payload',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'importe_centavos' => 'integer',
            'neto_centavos' => 'integer',
            'aprobado_at' => 'datetime',
            'acreditado_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }
}
