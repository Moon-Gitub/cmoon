<?php

namespace App\Services;

use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\MovimientoCuenta;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AlertaService
{
    /**
     * @return list<array{tipo: string, severidad: string, titulo: string, detalle: string, url?: string|null}>
     */
    public function paraEmpresa(?int $empresaId = null): array
    {
        $alertas = [];
        $hoy = Carbon::today();

        $productos = Producto::query()
            ->when($empresaId !== null, fn ($q) => $q->withoutGlobalScopes()->where('empresa_id', $empresaId))
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->withSum('stocks as stock_total', 'cantidad')
            ->havingRaw('COALESCE(stock_total, 0) <= stock_minimo')
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'codigo', 'nombre', 'stock_minimo']);

        foreach ($productos as $p) {
            $stock = (float) ($p->stock_total ?? 0);
            $alertas[] = [
                'tipo' => 'stock_bajo',
                'severidad' => $stock <= 0 ? 'alta' : 'media',
                'titulo' => 'Stock bajo: '.$p->nombre,
                'detalle' => sprintf('Stock %.2f / mínimo %.2f (%s)', $stock, (float) $p->stock_minimo, $p->codigo),
                'url' => route('productos.stock', $p),
            ];
        }

        $morphCliente = (new Cliente)->getMorphClass();
        $movimientos = MovimientoCuenta::query()
            ->with('titular')
            ->where('titular_type', $morphCliente)
            ->where('importe', '>', 0)
            ->when($empresaId !== null, function ($q) use ($empresaId) {
                $q->whereIn('titular_id', Cliente::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->select('id'));
            })
            ->orderByDesc('fecha')
            ->limit(200)
            ->get();

        $vencidos = 0;
        foreach ($movimientos as $mov) {
            if ($vencidos >= 30) {
                break;
            }

            /** @var Cliente|null $cliente */
            $cliente = $mov->titular instanceof Cliente ? $mov->titular : null;
            if (! $cliente) {
                continue;
            }

            $vencimiento = $this->vencimientoMovimiento($mov, $cliente);
            if (! $vencimiento || $vencimiento->gt($hoy)) {
                continue;
            }

            $vencidos++;
            $alertas[] = [
                'tipo' => 'cc_vencida',
                'severidad' => $vencimiento->lt($hoy->copy()->subDays(15)) ? 'alta' : 'media',
                'titulo' => 'CC vencida: '.$cliente->nombre,
                'detalle' => sprintf(
                    '%s — $ %s (venc. %s)',
                    $mov->concepto ?: $mov->tipo,
                    number_format((float) $mov->importe, 2, ',', '.'),
                    $vencimiento->format('d/m/Y')
                ),
                'url' => route('clientes.cuenta', $cliente),
            ];
        }

        $limiteCaja = now()->subHours(16);
        $sesiones = CajaSesion::query()
            ->with(['caja.sucursal', 'usuario'])
            ->where('estado', 'abierta')
            ->where('abierta_at', '<', $limiteCaja)
            ->when($empresaId !== null, function ($q) use ($empresaId) {
                $q->whereHas('caja.sucursal', fn ($s) => $s->where('empresa_id', $empresaId));
            })
            ->orderBy('abierta_at')
            ->limit(20)
            ->get();

        foreach ($sesiones as $sesion) {
            $horas = (int) $sesion->abierta_at->diffInHours(now());
            $alertas[] = [
                'tipo' => 'caja_abierta',
                'severidad' => $horas >= 24 ? 'alta' : 'media',
                'titulo' => 'Caja abierta hace '.$horas.'h',
                'detalle' => ($sesion->caja?->nombre ?? 'Caja').' — '.$sesion->usuario?->name,
                'url' => route('cajas.sesion', $sesion),
            ];
        }

        $chequeClass = \App\Models\Cheque::class;
        if (Schema::hasTable('cheques') && class_exists($chequeClass)) {
            $hasta = $hoy->copy()->addDays(7);
            $cheques = $chequeClass::query()
                ->when($empresaId !== null, fn ($q) => $q->withoutGlobalScopes()->where('empresa_id', $empresaId))
                ->where('estado', 'en_cartera')
                ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $hasta->toDateString()])
                ->orderBy('fecha_vencimiento')
                ->limit(20)
                ->get();

            foreach ($cheques as $cheque) {
                $alertas[] = [
                    'tipo' => 'cheque_por_vencer',
                    'severidad' => 'baja',
                    'titulo' => 'Cheque por vencer N° '.$cheque->numero,
                    'detalle' => sprintf(
                        '%s — $ %s (venc. %s)',
                        $cheque->banco ?: 'Sin banco',
                        number_format((float) $cheque->importe, 2, ',', '.'),
                        $cheque->fecha_vencimiento?->format('d/m/Y')
                    ),
                    'url' => null,
                ];
            }
        }

        return $alertas;
    }

    private function vencimientoMovimiento(MovimientoCuenta $mov, Cliente $cliente): ?Carbon
    {
        if ($mov->vencimiento ?? null) {
            return Carbon::parse($mov->vencimiento)->startOfDay();
        }

        $dias = (int) ($cliente->dias_credito ?? 0);
        if ($dias <= 0 || ! $mov->fecha) {
            return null;
        }

        return Carbon::parse($mov->fecha)->addDays($dias)->startOfDay();
    }
}
