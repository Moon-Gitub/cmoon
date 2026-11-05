<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $table = 'billing_customers';

    protected $fillable = [
        'legacy_id', 'nombre', 'razon_social', 'cuit', 'email', 'telefono', 'dominio',
        'plataforma', 'estado', 'modo_cobro', 'aplicar_recargos', 'prorroga_hasta',
        'pay_token', 'notas',
    ];

    protected $hidden = ['api_key_hash'];

    protected $attributes = [
        'plataforma' => 'legacy',
        'estado' => 'activo',
        'modo_cobro' => 'solo_aviso',
        'aplicar_recargos' => true,
    ];

    protected function casts(): array
    {
        return [
            'aplicar_recargos' => 'boolean',
            'prorroga_hasta' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $c) {
            $c->pay_token ??= self::nuevoToken();
        });
    }

    public static function nuevoToken(): string
    {
        return Str::random(40);
    }

    /** Devuelve la key en claro una sola vez; solo se guarda el hash. */
    public function regenerarApiKey(): string
    {
        $key = 'mbk_'.Str::random(48);
        $this->forceFill(['api_key_hash' => hash('sha256', $key)])->save();

        return $key;
    }

    public static function porApiKey(string $key): ?self
    {
        return $key === '' ? null : self::where('api_key_hash', hash('sha256', $key))->first();
    }

    public function payUrl(): string
    {
        return config('billing.public_url').'/pagar/'.$this->pay_token;
    }

    public function posUrl(): ?string
    {
        if (! $this->dominio || $this->dominio === 'localhost') {
            return null;
        }

        return str_starts_with($this->dominio, 'http') ? $this->dominio : 'https://'.$this->dominio;
    }

    public function activo(): bool
    {
        return $this->estado === 'activo';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(Checkout::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function abonoMensualCentavos(): int
    {
        return (int) $this->subscriptions()->where('activa', true)->sum('importe_centavos');
    }
}
