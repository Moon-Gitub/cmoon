<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comprobante extends Model
{
    protected $table = 'comprobantes';

    public const TIPOS = [
        1 => 'Factura A',
        6 => 'Factura B',
        11 => 'Factura C',
        3 => 'Nota de Crédito A',
        8 => 'Nota de Crédito B',
        13 => 'Nota de Crédito C',
        2 => 'Nota de Débito A',
        7 => 'Nota de Débito B',
        12 => 'Nota de Débito C',
    ];

    protected $fillable = [
        'venta_id',
        'comprobante_asociado_id',
        'emisor_id',
        'punto_venta_id',
        'user_id',
        'tipo_comprobante',
        'numero',
        'doc_tipo',
        'doc_numero',
        'receptor_nombre',
        'receptor_condicion_iva',
        'neto',
        'iva',
        'exento',
        'no_gravado',
        'total',
        'detalle_iva',
        'detalle_items',
        'concepto',
        'cae',
        'cae_vencimiento',
        'estado',
        'mensaje_afip',
        'respuesta_afip',
        'fecha_emision',
    ];

    protected function casts(): array
    {
        return [
            'neto' => 'decimal:2',
            'iva' => 'decimal:2',
            'exento' => 'decimal:2',
            'no_gravado' => 'decimal:2',
            'total' => 'decimal:2',
            'detalle_iva' => 'array',
            'detalle_items' => 'array',
            'respuesta_afip' => 'array',
            'cae_vencimiento' => 'date',
            'fecha_emision' => 'date',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function comprobanteAsociado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'comprobante_asociado_id');
    }

    public function esNota(): bool
    {
        return in_array($this->tipo_comprobante, [2, 3, 7, 8, 12, 13], true);
    }

    /** Ítems a mostrar en la impresión: de la venta o los libres */
    public function items(): array
    {
        if ($this->venta) {
            return $this->venta->items->map(fn ($i) => [
                'descripcion' => $i->descripcion,
                'cantidad' => (float) $i->cantidad,
                'precio_unitario' => (float) $i->precio_unitario,
                'total' => (float) $i->total,
            ])->all();
        }

        return $this->detalle_items ?? [];
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipoNombre(): string
    {
        return self::TIPOS[$this->tipo_comprobante] ?? "Tipo {$this->tipo_comprobante}";
    }

    public function numeroFormateado(): string
    {
        return sprintf('%04d-%08d', $this->puntoVenta->numero, $this->numero ?? 0);
    }

    public function letra(): string
    {
        return match (true) {
            in_array($this->tipo_comprobante, [1, 2, 3]) => 'A',
            in_array($this->tipo_comprobante, [6, 7, 8]) => 'B',
            in_array($this->tipo_comprobante, [11, 12, 13]) => 'C',
            default => 'X',
        };
    }
}
