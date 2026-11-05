<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Libro mayor inmutable: + aumenta la deuda, − la reduce.
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'billing_ledger_entries';

    protected $fillable = [
        'customer_id', 'fecha', 'tipo', 'descripcion', 'importe_centavos', 'referencia',
        'payment_id', 'invoice_id', 'origen', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'importe_centavos' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El libro mayor es inmutable: registrá un ajuste.'));
        static::deleting(fn () => throw new LogicException('El libro mayor es inmutable: registrá un ajuste.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
