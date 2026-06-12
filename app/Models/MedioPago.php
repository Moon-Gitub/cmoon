<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedioPago extends Model
{
    protected $table = 'medios_pago';

    protected $fillable = ['empresa_id', 'nombre', 'tipo', 'recargo_porcentaje', 'activo'];

    protected function casts(): array
    {
        return [
            'recargo_porcentaje' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function esCuentaCorriente(): bool
    {
        return $this->tipo === 'cuenta_corriente';
    }
}
