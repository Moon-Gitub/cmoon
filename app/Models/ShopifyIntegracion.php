<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopifyIntegracion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'shopify_integraciones';

    protected $fillable = [
        'empresa_id',
        'store_domain',
        'access_token',
        'api_key',
        'api_secret',
        'webhook_secret',
        'store_name',
        'api_version',
        'scopes',
        'sync_products',
        'sync_orders',
        'auto_create_products',
        'push_products',
        'default_sucursal_id',
        'last_product_sync_at',
        'last_order_sync_at',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'api_secret' => 'encrypted',
            'scopes' => 'array',
            'sync_products' => 'boolean',
            'sync_orders' => 'boolean',
            'auto_create_products' => 'boolean',
            'push_products' => 'boolean',
            'activo' => 'boolean',
            'last_product_sync_at' => 'datetime',
            'last_order_sync_at' => 'datetime',
        ];
    }

    public function sucursalDefault(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'default_sucursal_id');
    }

    public function productMaps(): HasMany
    {
        return $this->hasMany(ShopifyProductMap::class, 'integracion_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ShopifyLog::class, 'integracion_id');
    }

    public function normalizedDomain(): string
    {
        $domain = strtolower(trim($this->store_domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        if (! str_contains($domain, '.')) {
            $domain .= '.myshopify.com';
        }

        return $domain;
    }

    public function apiBaseUrl(): string
    {
        $version = $this->api_version ?: config('shopify.api_version');

        return 'https://'.$this->normalizedDomain().'/admin/api/'.$version;
    }

    public function hmacSecret(): string
    {
        return (string) (
            $this->webhook_secret
            ?: $this->api_secret
            ?: config('shopify.webhook_secret')
            ?: config('shopify.api_secret')
            ?: ''
        );
    }
}
