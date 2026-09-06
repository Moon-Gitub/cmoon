<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'cheques';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'proveedor_id',
        'numero',
        'banco',
        'plaza',
        'fecha_emision',
        'fecha_vencimiento',
        'importe',
        'tipo',
        'estado',
        'observaciones',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'importe' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
