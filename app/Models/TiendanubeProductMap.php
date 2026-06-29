<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeProductMap extends Model
{
    protected $table = 'tiendanube_product_maps';

    protected $fillable = [
        'integracion_id',
        'producto_id',
        'tn_product_id',
        'tn_variant_id',
        'tn_sku',
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
        return $this->belongsTo(TiendanubeIntegracion::class, 'integracion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
