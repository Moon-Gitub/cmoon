<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use PerteneceAEmpresa;

    use SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'razon_social',
        'cuit',
        'condicion_iva',
        'email',
        'telefono',
        'domicilio',
        'localidad',
        'alicuota_retencion_iibb',
        'observaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'alicuota_retencion_iibb' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function movimientosCuenta(): MorphMany
    {
        return $this->morphMany(MovimientoCuenta::class, 'titular');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Saldo de cta. cte.: positivo = le debemos al proveedor.
     */
    public function saldoCuenta(): float
    {
        return (float) $this->movimientosCuenta()->sum('importe');
    }
}
