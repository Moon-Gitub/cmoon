<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remito extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'remitos';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'cliente_id',
        'presupuesto_id',
        'venta_id',
        'user_id',
        'numero',
        'estado',
        'fecha',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RemitoItem::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
