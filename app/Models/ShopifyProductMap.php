<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyProductMap extends Model
{
    protected $table = 'shopify_product_maps';

    protected $fillable = [
        'integracion_id',
        'producto_id',
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_sku',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(ShopifyIntegracion::class, 'integracion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
