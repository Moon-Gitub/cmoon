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
        'color_primario',
        'activa',
        'agente_retencion_iibb',
        'codigo_jurisdiccion_iibb',
        'tipo_regimen_retencion_default',
        'proximo_numero_recibo',
    ];

    protected function casts(): array
    {
        return [
            'inicio_actividades' => 'date',
            'activa' => 'boolean',
            'agente_retencion_iibb' => 'boolean',
            'codigo_jurisdiccion_iibb' => 'integer',
            'tipo_regimen_retencion_default' => 'integer',
            'proximo_numero_recibo' => 'integer',
        ];
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
