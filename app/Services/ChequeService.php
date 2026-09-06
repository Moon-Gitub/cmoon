<?php

namespace App\Services;

use App\Models\Cheque;
use InvalidArgumentException;

class ChequeService
{
    /**
     * @param  array{
     *   empresa_id?: int,
     *   cliente_id?: int|null,
     *   proveedor_id?: int|null,
     *   numero: string,
     *   banco?: string|null,
     *   plaza?: string|null,
     *   fecha_emision: string,
     *   fecha_vencimiento: string,
     *   importe: float,
     *   tipo: string,
     *   estado?: string,
     *   observaciones?: string|null,
     *   user_id?: int|null
     * }  $datos
     */
    public function crear(array $datos): Cheque
    {
        $tipo = $datos['tipo'] ?? 'recibido';
        if (! in_array($tipo, ['recibido', 'emitido'], true)) {
            throw new InvalidArgumentException('Tipo de cheque inválido.');
        }

        return Cheque::create([
            'empresa_id' => $datos['empresa_id'] ?? auth()->user()->empresa_id,
            'cliente_id' => $datos['cliente_id'] ?? null,
            'proveedor_id' => $datos['proveedor_id'] ?? null,
            'numero' => $datos['numero'],
            'banco' => $datos['banco'] ?? null,
            'plaza' => $datos['plaza'] ?? null,
            'fecha_emision' => $datos['fecha_emision'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'importe' => round((float) $datos['importe'], 2),
            'tipo' => $tipo,
            'estado' => $datos['estado'] ?? 'en_cartera',
            'observaciones' => $datos['observaciones'] ?? null,
            'user_id' => $datos['user_id'] ?? auth()->id(),
        ]);
    }

    public function cambiarEstado(Cheque $cheque, string $estado): Cheque
    {
        $validos = ['en_cartera', 'depositado', 'cobrado', 'rechazado', 'anulado'];
        if (! in_array($estado, $validos, true)) {
            throw new InvalidArgumentException('Estado de cheque inválido.');
        }

        $cheque->update(['estado' => $estado]);

        return $cheque->fresh();
    }
}
