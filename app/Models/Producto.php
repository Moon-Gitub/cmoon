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

    /** @var array<string, mixed> */
    protected $attributes = [
        'alicuota_iva' => 21,
        'unidad' => 'UN',
        'activo' => true,
    ];

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
        'precio_promocional',
        'promo_desde',
        'promo_hasta',
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
            'precio_promocional' => 'decimal:2',
            'promo_desde' => 'date',
            'promo_hasta' => 'date',
            'alicuota_iva' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
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

    public function lotes(): HasMany
    {
        return $this->hasMany(ProductoLote::class);
    }

    public function stockTotal(): float
    {
        return (float) $this->stocks->sum('cantidad');
    }

    public function stockEn(int $sucursalId): float
    {
        return (float) ($this->stocks->firstWhere('sucursal_id', $sucursalId)?->cantidad ?? 0);
    }

    public function promoActiva(): bool
    {
        $promo = (float) ($this->precio_promocional ?? 0);
        if ($promo <= 0) {
            return false;
        }

        $hoy = now()->startOfDay();
        if ($this->promo_desde && $hoy->lt($this->promo_desde->copy()->startOfDay())) {
            return false;
        }
        if ($this->promo_hasta && $hoy->gt($this->promo_hasta->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    /** Precio a mostrar en góndola (promo vigente o venta). */
    public function precioGondola(): float
    {
        if ($this->promoActiva()) {
            return (float) $this->precio_promocional;
        }

        return (float) $this->precio_venta;
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
