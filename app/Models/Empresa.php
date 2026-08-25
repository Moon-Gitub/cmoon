<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'razon_social',
        'nombre_fantasia',
        'cuit',
        'condicion_iva',
        'ingresos_brutos',
        'inicio_actividades',
        'domicilio',
        'localidad',
        'provincia',
        'codigo_postal',
        'telefono',
        'email',
        'logo_path',
        'catalogo_fondo_path',
        'catalogo_logo_path',
        'catalogo_color_titulo',
        'catalogo_color_texto',
        'catalogo_share_token',
        'color_primario',
        'cotizacion_dolar',
        'activa',
        'agente_retencion_iibb',
        'codigo_jurisdiccion_iibb',
        'tipo_regimen_retencion_default',
        'proximo_numero_recibo',
        'ia_plan',
        'ia_abono_hasta',
        'ia_cupo_override',
        'ia_abono_solicitado_at',
    ];

    protected function casts(): array
    {
        return [
            'inicio_actividades' => 'date',
            'cotizacion_dolar' => 'decimal:2',
            'activa' => 'boolean',
            'agente_retencion_iibb' => 'boolean',
            'codigo_jurisdiccion_iibb' => 'integer',
            'tipo_regimen_retencion_default' => 'integer',
            'proximo_numero_recibo' => 'integer',
            'ia_abono_hasta' => 'date',
            'ia_abono_solicitado_at' => 'datetime',
        ];
    }

    public function abonoIaVigente(): bool
    {
        if ($this->ia_plan !== 'abono') {
            return false;
        }

        return $this->ia_abono_hasta === null || $this->ia_abono_hasta->gte(now()->startOfDay());
    }

    public function cupoIaMensual(): int
    {
        if ($this->ia_cupo_override) {
            return (int) $this->ia_cupo_override;
        }

        return $this->abonoIaVigente()
            ? (int) config('ia.cupo_abono')
            : (int) config('ia.cupo_incluido');
    }

    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
