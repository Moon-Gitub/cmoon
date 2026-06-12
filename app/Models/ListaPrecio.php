<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaPrecio extends Model
{
    protected $table = 'listas_precio';

    protected $fillable = ['empresa_id', 'nombre', 'porcentaje', 'activa'];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    public function precioPara(Producto $producto): float
    {
        return round((float) $producto->precio_venta * (1 + (float) $this->porcentaje / 100), 2);
    }
}
