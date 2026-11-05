<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'billing_notifications';

    protected $fillable = ['customer_id', 'tipo', 'periodo', 'canal', 'destino', 'estado', 'error'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
