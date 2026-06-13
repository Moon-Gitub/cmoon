<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multi-empresa: cada usuario solo ve y crea datos de su propia empresa.
 * Aplica un global scope por empresa_id del usuario autenticado y lo
 * completa automáticamente al crear registros.
 */
trait PerteneceAEmpresa
{
    public static function bootPerteneceAEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (auth()->check() && auth()->user()->empresa_id) {
                $builder->where($builder->getModel()->getTable().'.empresa_id', auth()->user()->empresa_id);
            }
        });

        static::creating(function ($modelo) {
            if (! $modelo->empresa_id && auth()->check()) {
                $modelo->empresa_id = auth()->user()->empresa_id;
            }
        });
    }
}
