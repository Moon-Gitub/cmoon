<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visita extends Model
{
    use PerteneceAEmpresa;

    protected $fillable = [
        'uuid',
        'empresa_id',
        'cliente_id',
        'user_id',
        'ruta_id',
        'estado',
        'fecha',
        'lat',
        'lng',
        'observaciones',
        'checkin_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'checkin_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }
}
