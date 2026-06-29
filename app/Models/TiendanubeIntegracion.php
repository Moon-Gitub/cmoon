<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubeIntegracion extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'tiendanube_integraciones';

    protected $fillable = [
        'empresa_id',
        'store_id',
        'access_token',
        'store_name',
        'store_url',
        'scopes',
        'sync_products',
        'sync_stock',
        'sync_orders',
        'sync_customers',
        'auto_create_products',
        'default_sucursal_id',
        'webhook_secret',
        'last_product_sync_at',
        'last_stock_sync_at',
        'last_order_sync_at',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'scopes' => 'array',
            'sync_products' => 'boolean',
            'sync_stock' => 'boolean',
            'sync_orders' => 'boolean',
            'sync_customers' => 'boolean',
            'auto_create_products' => 'boolean',
            'activo' => 'boolean',
            'last_product_sync_at' => 'datetime',
            'last_stock_sync_at' => 'datetime',
            'last_order_sync_at' => 'datetime',
        ];
    }

    public function sucursalDefault(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'default_sucursal_id');
    }

    public function productMaps(): HasMany
    {
        return $this->hasMany(TiendanubeProductMap::class, 'integracion_id');
    }

    public function categoryMaps(): HasMany
    {
        return $this->hasMany(TiendanubeCategoryMap::class, 'integracion_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TiendanubeLog::class, 'integracion_id');
    }

    public function apiBaseUrl(): string
    {
        return "https://api.tiendanube.com/v1/{$this->store_id}";
    }

    public function generateWebhookSecret(): string
    {
        $this->webhook_secret = bin2hex(random_bytes(32));
        $this->save();

        return $this->webhook_secret;
    }
}
