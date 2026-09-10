<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaCompra extends Model
{
    protected $table = 'ia_compras';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'paquete_id',
        'creditos',
        'precio',
        'estado',
        'notas',
        'aprobado_por',
        'aprobado_at',
    ];

    protected function casts(): array
    {
        return [
            'creditos' => 'integer',
            'precio' => 'decimal:2',
            'aprobado_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(IaPaquete::class, 'paquete_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
