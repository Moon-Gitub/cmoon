<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class ListaPrecio extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'listas_precio';

    protected $fillable = ['empresa_id', 'nombre', 'porcentaje', 'base', 'activa'];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    public function usaPrecioCompra(): bool
    {
        return ($this->base ?? 'venta') === 'compra';
    }

    public function precioPara(Producto $producto): float
    {
        $base = $this->usaPrecioCompra()
            ? (float) $producto->precio_compra
            : (float) $producto->precio_venta;

        return round($base * (1 + (float) $this->porcentaje / 100), 2);
    }
}
