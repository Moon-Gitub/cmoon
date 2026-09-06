<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaBancaria extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'banco',
        'cbu',
        'alias',
        'moneda',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoBancario::class);
    }
}
