<?php

namespace App\Events;

use App\Models\Producto;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Producto $producto,
        public int $sucursalId,
        public float $cantidad,
    ) {}
}
