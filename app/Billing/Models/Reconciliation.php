<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $table = 'billing_reconciliations';

    protected $fillable = ['desde', 'hasta', 'estado', 'revisados', 'acreditados', 'alertas'];

    protected function casts(): array
    {
        return [
            'desde' => 'datetime',
            'hasta' => 'datetime',
            'alertas' => 'array',
        ];
    }
}
