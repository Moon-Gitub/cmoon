<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use PerteneceAEmpresa;

    protected $table = 'ventas';

    public const TIPO_VENTA = 'venta';

    public const TIPO_DEVOLUCION = 'devolucion';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'sucursal_id',
        'caja_sesion_id',
        'cliente_id',
        'user_id',
        'numero',
        'estado',
        'origen',
        'tipo',
        'venta_origen_id',
        'venta_origen_numero',
        'tn_order_id',
        'tn_order_number',
        'shopify_order_id',
        'shopify_order_number',
        'subtotal',
        'descuento',
        'recargo',
        'total',
        'motivo_anulacion',
        'anulada_at',
        'anulada_por',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'recargo' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class);
    }

    public function ventaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'venta_origen_id');
    }

    public function esDevolucion(): bool
    {
        return $this->tipo === self::TIPO_DEVOLUCION;
    }

    public function totalConSigno(): float
    {
        $total = (float) $this->total;

        return $this->esDevolucion() ? -abs($total) : $total;
    }

    public static function sqlTotalConSigno(string $tabla = 'ventas'): string
    {
        return "CASE WHEN {$tabla}.tipo = 'devolucion' THEN -ABS({$tabla}.total) ELSE {$tabla}.total END";
    }

    public static function sqlImportePagoConSigno(string $ventas = 'ventas', string $pagos = 'venta_pagos'): string
    {
        return "CASE WHEN {$ventas}.tipo = 'devolucion' THEN -ABS({$pagos}.importe) ELSE {$pagos}.importe END";
    }

    public static function sqlCantidadItemConSigno(string $ventas = 'ventas', string $items = 'venta_items'): string
    {
        return "CASE WHEN {$ventas}.tipo = 'devolucion' THEN -ABS({$items}.cantidad) ELSE {$items}.cantidad END";
    }

    public static function sqlTotalItemConSigno(string $ventas = 'ventas', string $items = 'venta_items'): string
    {
        return "CASE WHEN {$ventas}.tipo = 'devolucion' THEN -ABS({$items}.total) ELSE {$items}.total END";
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    public function yaFacturada(): bool
    {
        return $this->comprobantes()
            ->whereIn('estado', ['autorizado', 'pendiente'])
            ->exists();
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }
}
