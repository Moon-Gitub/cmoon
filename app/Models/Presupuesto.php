<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presupuesto extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'presupuestos';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'user_id',
        'venta_id',
        'numero',
        'estado',
        'total',
        'valido_hasta',
        'observaciones',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'valido_hasta' => 'date',
            'fecha' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PresupuestoItem::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
