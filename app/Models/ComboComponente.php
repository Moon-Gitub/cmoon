<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboComponente extends Model
{
    protected $table = 'combo_componentes';

    protected $fillable = ['combo_id', 'componente_id', 'cantidad'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3'];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'combo_id');
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'componente_id');
    }
}
