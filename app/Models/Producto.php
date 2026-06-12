<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'codigo',
        'nombre',
        'descripcion',
        'unidad',
        'pesable',
        'precio_compra',
        'precio_venta',
        'alicuota_iva',
        'stock_minimo',
        'imagen_path',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'pesable' => 'boolean',
            'activo' => 'boolean',
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'alicuota_iva' => 'decimal:2',
            'stock_minimo' => 'decimal:3',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movimientosStock(): HasMany
    {
        return $this->hasMany(MovimientoStock::class);
    }

    public function stockTotal(): float
    {
        return (float) $this->stocks->sum('cantidad');
    }

    public function stockEn(int $sucursalId): float
    {
        return (float) ($this->stocks->firstWhere('sucursal_id', $sucursalId)?->cantidad ?? 0);
    }
}
