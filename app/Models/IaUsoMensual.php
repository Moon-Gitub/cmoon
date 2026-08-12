<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaUsoMensual extends Model
{
    protected $table = 'ia_uso_mensual';

    protected $fillable = [
        'empresa_id',
        'periodo',
        'usados',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
