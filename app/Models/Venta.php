<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'ventas';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'caja_sesion_id',
        'cliente_id',
        'user_id',
        'numero',
        'estado',
        'origen',
        'subtotal',
        'descuento',
        'recargo',
        'total',
        'motivo_anulacion',
        'anulada_at',
        'anulada_por',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'recargo' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class);
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }
}
