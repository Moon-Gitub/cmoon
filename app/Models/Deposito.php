<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deposito extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'depositos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(ProductoLote::class);
    }
}
