<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MovimientoCuenta;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CobranzaService
{
    public function agenda(?Carbon $hasta = null): Collection
    {
        $hasta = ($hasta ?? now())->copy()->endOfDay();
        $hoy = Carbon::today();

        $clientes = Cliente::query()
            ->where('activo', true)
            ->with(['movimientosCuenta' => fn ($q) => $q->orderByDesc('fecha')->orderByDesc('id')])
            ->get()
            ->map(function (Cliente $cliente) use ($hoy) {
                $saldo = (float) $cliente->movimientosCuenta->sum('importe');
                if ($saldo <= 0) {
                    return null;
                }

                $ultimo = $cliente->movimientosCuenta->first();
                $proximo = $this->proximoVencimiento($cliente);
                $diasAtraso = $this->diasAtraso($proximo, $hoy, $saldo);

                return (object) [
                    'cliente' => $cliente,
                    'cliente_id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'saldo' => round($saldo, 2),
                    'ultimo_movimiento' => $ultimo,
                    'proximo_vencimiento' => $proximo,
                    'dias_atraso' => $diasAtraso,
                ];
            })
            ->filter()
            ->filter(function ($row) use ($hasta) {
                if (! $row->proximo_vencimiento) {
                    return true;
                }

                return $row->proximo_vencimiento->lte($hasta);
            })
            ->sortByDesc('dias_atraso')
            ->values();

        return $clientes;
    }

    public function diasAtraso(?Carbon $vencimiento, ?Carbon $hoy = null, float $saldo = 0): int
    {
        if ($saldo <= 0 || ! $vencimiento) {
            return 0;
        }

        $hoy = ($hoy ?? Carbon::today())->copy()->startOfDay();
        $venc = $vencimiento->copy()->startOfDay();

        if ($venc->gte($hoy)) {
            return 0;
        }

        return (int) $venc->diffInDays($hoy);
    }

    public function proximoVencimiento(Cliente $cliente): ?Carbon
    {
        $hoy = Carbon::today();
        $candidatos = [];

        foreach ($cliente->movimientosCuenta as $mov) {
            if ((float) $mov->importe <= 0) {
                continue;
            }

            $venc = $this->vencimientoDe($mov, $cliente);
            if ($venc) {
                $candidatos[] = $venc;
            }
        }

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, fn (Carbon $a, Carbon $b) => $a <=> $b);

        foreach ($candidatos as $venc) {
            if ($venc->lte($hoy)) {
                return $venc;
            }
        }

        return $candidatos[0];
    }

    private function vencimientoDe(MovimientoCuenta $mov, Cliente $cliente): ?Carbon
    {
        if ($mov->vencimiento) {
            return Carbon::parse($mov->vencimiento)->startOfDay();
        }

        $dias = (int) ($cliente->dias_credito ?? 0);
        if ($dias > 0 && $mov->fecha) {
            return Carbon::parse($mov->fecha)->addDays($dias)->startOfDay();
        }

        return $mov->fecha ? Carbon::parse($mov->fecha)->startOfDay() : null;
    }
}
