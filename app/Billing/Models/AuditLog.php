<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'billing_audit_logs';

    protected $fillable = ['customer_id', 'user_id', 'accion', 'nivel', 'detalle', 'ip'];

    protected function casts(): array
    {
        return ['detalle' => 'array'];
    }

    public static function registrar(string $accion, array $detalle = [], ?int $customerId = null, string $nivel = 'info'): self
    {
        $request = app()->runningInConsole() ? null : request();

        return self::create([
            'customer_id' => $customerId,
            'user_id' => $request?->user()?->id,
            'accion' => $accion,
            'nivel' => $nivel,
            'detalle' => $detalle,
            'ip' => $request?->ip(),
        ]);
    }
}
