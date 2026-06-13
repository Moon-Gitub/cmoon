<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use PerteneceAEmpresa;

    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo_documento',
        'documento',
        'condicion_iva',
        'email',
        'telefono',
        'domicilio',
        'localidad',
        'lista_precio_id',
        'limite_credito',
        'observaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'limite_credito' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class);
    }

    public function movimientosCuenta(): MorphMany
    {
        return $this->morphMany(MovimientoCuenta::class, 'titular');
    }

    /**
     * Saldo de cta. cte.: positivo = el cliente nos debe.
     */
    public function saldoCuenta(): float
    {
        return (float) $this->movimientosCuenta()->sum('importe');
    }
}
