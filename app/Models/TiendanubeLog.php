<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeLog extends Model
{
    protected $table = 'tiendanube_logs';

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
        return $this->belongsTo(TiendanubeIntegracion::class, 'integracion_id');
    }

    public static function registrar(
        TiendanubeIntegracion $integracion,
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
