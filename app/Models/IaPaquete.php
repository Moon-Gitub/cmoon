<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IaPaquete extends Model
{
    protected $table = 'ia_paquetes';

    protected $fillable = [
        'nombre',
        'creditos',
        'precio',
        'moneda',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'creditos' => 'integer',
            'precio' => 'decimal:2',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(IaCompra::class, 'paquete_id');
    }
}
