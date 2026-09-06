<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaRecurrente extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'facturas_recurrentes';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'emisor_id',
        'dia_mes',
        'proximo_vencimiento',
        'concepto',
        'monto',
        'activa',
        'ultimo_envio_at',
    ];

    protected function casts(): array
    {
        return [
            'dia_mes' => 'integer',
            'proximo_vencimiento' => 'date',
            'monto' => 'decimal:2',
            'activa' => 'boolean',
            'ultimo_envio_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }
}
