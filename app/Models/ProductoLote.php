<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoLote extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'producto_lotes';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'deposito_id',
        'codigo_lote',
        'serie',
        'vencimiento',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'vencimiento' => 'date',
            'cantidad' => 'decimal:3',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }
}
