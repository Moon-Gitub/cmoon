<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Emisor extends Model
{
    protected $table = 'emisores';

    protected $fillable = [
        'empresa_id',
        'razon_social',
        'cuit',
        'condicion_iva',
        'ingresos_brutos',
        'inicio_actividades',
        'domicilio',
        'certificado_path',
        'clave_privada_path',
        'entorno',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'inicio_actividades' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    public function esMonotributo(): bool
    {
        return $this->condicion_iva === 'MONOTRIBUTO';
    }

    public function tieneCertificado(): bool
    {
        return $this->certificado_path !== null && $this->clave_privada_path !== null;
    }

    public function esProduccion(): bool
    {
        return $this->entorno === 'produccion';
    }
}
