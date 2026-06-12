<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaSesion extends Model
{
    protected $table = 'caja_sesiones';

    protected $fillable = [
        'caja_id',
        'user_id',
        'monto_apertura',
        'monto_cierre_declarado',
        'monto_cierre_sistema',
        'estado',
        'observaciones',
        'abierta_at',
        'cerrada_at',
    ];

    protected function casts(): array
    {
        return [
            'monto_apertura' => 'decimal:2',
            'monto_cierre_declarado' => 'decimal:2',
            'monto_cierre_sistema' => 'decimal:2',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Efectivo esperado en caja: apertura + ventas en efectivo + ingresos - egresos.
     */
    public function efectivoEsperado(): float
    {
        $ventasEfectivo = (float) VentaPago::query()
            ->whereHas('venta', fn ($q) => $q
                ->where('caja_sesion_id', $this->id)
                ->where('estado', 'completada'))
            ->whereHas('medioPago', fn ($q) => $q->where('tipo', 'efectivo'))
            ->sum('importe');

        $ingresos = (float) $this->movimientos()->where('tipo', 'ingreso')->sum('importe');
        $egresos = (float) $this->movimientos()->where('tipo', 'egreso')->sum('importe');

        return round((float) $this->monto_apertura + $ventasEfectivo + $ingresos - $egresos, 2);
    }
}
