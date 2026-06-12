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
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'inicio_actividades' => 'date',
            'activa' => 'boolean',
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
