<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeCategoryMap extends Model
{
    protected $table = 'tiendanube_category_maps';

    protected $fillable = [
        'integracion_id',
        'categoria_id',
        'tn_category_id',
    ];

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(TiendanubeIntegracion::class, 'integracion_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
