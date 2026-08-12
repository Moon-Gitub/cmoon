<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BalanzaFormato extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'balanzas_formatos';

    protected $fillable = [
        'empresa_id', 'nombre', 'prefijo', 'longitud_min', 'longitud_max',
        'pos_producto', 'longitud_producto', 'modo_cantidad',
        'pos_cantidad', 'longitud_cantidad', 'factor_divisor', 'cantidad_fija',
        'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'longitud_min' => 'integer',
            'longitud_max' => 'integer',
            'pos_producto' => 'integer',
            'longitud_producto' => 'integer',
            'pos_cantidad' => 'integer',
            'longitud_cantidad' => 'integer',
            'factor_divisor' => 'decimal:4',
            'cantidad_fija' => 'decimal:3',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** Config para el POS (mismo shape que demonew balanzasFormatosConfig). */
    public static function configParaVenta(?int $empresaId = null): Collection
    {
        $q = static::query()->where('activo', true)->orderBy('orden')->orderBy('id');
        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }

        return $q->get()->map(fn (self $f) => [
            'id' => $f->id,
            'prefijo' => $f->prefijo,
            'longitud_min' => $f->longitud_min,
            'longitud_max' => $f->longitud_max,
            'pos_producto' => $f->pos_producto,
            'longitud_producto' => $f->longitud_producto,
            'modo_cantidad' => $f->modo_cantidad,
            'pos_cantidad' => $f->pos_cantidad,
            'longitud_cantidad' => $f->longitud_cantidad,
            'factor_divisor' => (float) $f->factor_divisor,
            'cantidad_fija' => (float) $f->cantidad_fija,
        ]);
    }
}
