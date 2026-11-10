<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Datos de solo lectura del movimiento legacy. No mueve saldo. */
class LegacyDetalle extends Model
{
    protected $table = 'billing_legacy_detalles';

    protected $fillable = [
        'legacy_movimiento_id', 'customer_id', 'metodo_pago', 'numero_recibo', 'referencia_mp', 'origen',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function idDesdeReferencia(?string $referencia): ?int
    {
        return preg_match('/^legacy:(\d+)$/', (string) $referencia, $m) ? (int) $m[1] : null;
    }
}
