<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\MovimientoCuenta;
use App\Models\Proveedor;
use App\Models\Retencion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RetencionService
{
    /** @return array{monto: float, neto: float} */
    public function calcular(float $montoSujeto, float $alicuota, ?float $montoOverride = null): array
    {
        $monto = $montoOverride ?? round($montoSujeto * $alicuota / 100, 2);

        return [
            'monto' => max(0, round($monto, 2)),
            'neto' => max(0, round($montoSujeto - $monto, 2)),
        ];
    }

    public function reservarNumeroRecibo(Empresa $empresa): int
    {
        return DB::transaction(function () use ($empresa) {
            $empresa = Empresa::lockForUpdate()->findOrFail($empresa->id);
            $numero = (int) $empresa->proximo_numero_recibo;
            $empresa->update(['proximo_numero_recibo' => $numero + 1]);

            return $numero;
        });
    }

    public function crearDesdePago(
        Proveedor $proveedor,
        MovimientoCuenta $movimiento,
        User $user,
        array $datos,
    ): Retencion {
        $empresa = $proveedor->empresa ?? Empresa::findOrFail($proveedor->empresa_id);

        $calculo = $this->calcular(
            (float) $datos['monto_sujeto'],
            (float) $datos['alicuota'],
            isset($datos['monto']) ? (float) $datos['monto'] : null,
        );

        return Retencion::create([
            'empresa_id' => $proveedor->empresa_id,
            'proveedor_id' => $proveedor->id,
            'numero_recibo' => $this->reservarNumeroRecibo($empresa),
            'user_id' => $user->id,
            'movimiento_cuenta_id' => $movimiento->id,
            'factura_numero' => $datos['factura_numero'],
            'factura_neto' => (float) $datos['monto_sujeto'],
            'alicuota' => (float) $datos['alicuota'],
            'monto' => $calculo['monto'],
            'monto_neto_pagado' => $calculo['neto'],
            'fecha' => $datos['fecha'],
            'regimen' => (int) ($datos['regimen'] ?? $empresa->tipo_regimen_retencion_default),
            'jurisdiccion' => (int) ($datos['jurisdiccion'] ?? $empresa->codigo_jurisdiccion_iibb),
            'anulada' => false,
        ]);
    }

    public function defaultsEmpresa(Empresa $empresa): array
    {
        return [
            'regimen' => (int) $empresa->tipo_regimen_retencion_default,
            'jurisdiccion' => (int) $empresa->codigo_jurisdiccion_iibb,
        ];
    }
}
