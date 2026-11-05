<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkout extends Model
{
    public const PREFIJO = 'MB-';

    protected $table = 'billing_checkouts';

    protected $fillable = [
        'customer_id', 'external_reference', 'preference_id', 'init_point', 'modo', 'importe_centavos',
        'recargo_centavos', 'recargo_periodo', 'detalle', 'estado', 'expira_at', 'pagado_at',
    ];

    protected function casts(): array
    {
        return [
            'importe_centavos' => 'integer',
            'recargo_centavos' => 'integer',
            'detalle' => 'array',
            'expira_at' => 'datetime',
            'pagado_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function esReferenciaPropia(?string $ref): bool
    {
        return is_string($ref) && str_starts_with($ref, self::PREFIJO);
    }
}
