<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'billing_invoices';

    protected $fillable = ['customer_id', 'subscription_id', 'periodo', 'concepto', 'importe_centavos', 'vencimiento'];

    protected function casts(): array
    {
        return [
            'importe_centavos' => 'integer',
            'vencimiento' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
