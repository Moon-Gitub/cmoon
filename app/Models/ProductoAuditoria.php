<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAuditoria extends Model
{
    protected $table = 'producto_auditoria';

    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'user_id',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'origen',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
