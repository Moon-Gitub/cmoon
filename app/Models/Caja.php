<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = ['sucursal_id', 'nombre', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(CajaSesion::class);
    }

    public function sesionAbierta(): HasOne
    {
        return $this->hasOne(CajaSesion::class)->where('estado', 'abierta');
    }
}
