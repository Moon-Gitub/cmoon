<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraItem extends Model
{
    protected $table = 'orden_compra_items';

    protected $fillable = [
        'orden_compra_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'cantidad_recibida',
        'precio_unitario',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_recibida' => 'decimal:3',
            'precio_unitario' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
