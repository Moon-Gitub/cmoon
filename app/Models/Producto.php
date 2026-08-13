<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use PerteneceAEmpresa;

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
        'es_combo',
        'precio_compra',
        'precio_compra_dolar',
        'margen_ganancia',
        'precio_venta',
        'alicuota_iva',
        'stock_minimo',
        'imagen_path',
        'activo',
        'publicar_shopify',
        'publicar_whatsapp',
        'publicar_tiendanube',
    ];

    public const CANALES = [
        'shopify' => 'Shopify',
        'whatsapp' => 'WhatsApp',
        'tiendanube' => 'Tiendanube',
    ];

    protected function casts(): array
    {
        return [
            'pesable' => 'boolean',
            'es_combo' => 'boolean',
            'activo' => 'boolean',
            'publicar_shopify' => 'boolean',
            'publicar_whatsapp' => 'boolean',
            'publicar_tiendanube' => 'boolean',
            'precio_compra' => 'decimal:2',
            'precio_compra_dolar' => 'decimal:2',
            'margen_ganancia' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'alicuota_iva' => 'decimal:2',
            'stock_minimo' => 'decimal:3',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(ComboComponente::class, 'combo_id');
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

    public function scopePublicarEn($query, string $canal)
    {
        $columna = match ($canal) {
            'shopify' => 'publicar_shopify',
            'whatsapp' => 'publicar_whatsapp',
            'tiendanube' => 'publicar_tiendanube',
            default => null,
        };

        if ($columna === null) {
            return $query;
        }

        return $query->where($columna, true);
    }

    public function canalesActivos(): array
    {
        $activos = [];
        foreach (self::CANALES as $key => $label) {
            if ($this->getAttribute('publicar_'.$key)) {
                $activos[$key] = $label;
            }
        }

        return $activos;
    }
}
