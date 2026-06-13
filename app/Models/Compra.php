<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'compras';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'proveedor_id',
        'user_id',
        'factura_numero',
        'condicion',
        'total',
        'estado',
        'observaciones',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompraItem::class);
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
