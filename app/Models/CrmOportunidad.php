<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmOportunidad extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'crm_oportunidades';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'user_id',
        'titulo',
        'etapa',
        'monto',
        'probabilidad',
        'cierra_el',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'probabilidad' => 'integer',
            'cierra_el' => 'date',
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

    public function actividades(): HasMany
    {
        return $this->hasMany(CrmActividad::class, 'oportunidad_id');
    }
}
