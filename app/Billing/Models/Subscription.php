<?php

namespace App\Billing\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'billing_subscriptions';

    protected $fillable = ['customer_id', 'concepto', 'importe_centavos', 'desde', 'hasta', 'activa'];

    protected function casts(): array
    {
        return [
            'importe_centavos' => 'integer',
            'desde' => 'date',
            'hasta' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vigenteEn(CarbonInterface $fecha): bool
    {
        return $this->activa
            && $this->desde->lte($fecha->copy()->endOfMonth())
            && ($this->hasta === null || $this->hasta->gte($fecha->copy()->startOfMonth()));
    }
}
