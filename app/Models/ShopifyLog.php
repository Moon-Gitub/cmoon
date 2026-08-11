<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyLog extends Model
{
    protected $table = 'shopify_logs';

    protected $fillable = [
        'integracion_id',
        'tipo',
        'direccion',
        'entidad_tipo',
        'entidad_id',
        'request',
        'response',
        'status',
        'mensaje',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
        ];
    }

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(ShopifyIntegracion::class, 'integracion_id');
    }

    public static function registrar(
        ShopifyIntegracion $integracion,
        string $tipo,
        string $direccion,
        ?string $entidadTipo = null,
        ?int $entidadId = null,
        ?array $request = null,
        ?array $response = null,
        string $status = 'ok',
        ?string $mensaje = null,
    ): self {
        return self::create([
            'integracion_id' => $integracion->id,
            'tipo' => $tipo,
            'direccion' => $direccion,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'request' => $request,
            'response' => $response,
            'status' => $status,
            'mensaje' => $mensaje,
        ]);
    }
}
