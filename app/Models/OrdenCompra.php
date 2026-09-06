<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'proveedor_id',
        'user_id',
        'numero',
        'estado',
        'fecha',
        'observaciones',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
