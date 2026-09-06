<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemitoItem extends Model
{
    protected $table = 'remito_items';

    protected $fillable = [
        'remito_id',
        'producto_id',
        'descripcion',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
        ];
    }

    public function remito(): BelongsTo
    {
        return $this->belongsTo(Remito::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
