<?php

namespace App\Services;

use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class GestionDatosIaService
{
    public function __construct(
        private InformeService $informes,
    ) {}

    public function contexto(int $empresaId): string
    {
        $lineas = [];

        $ventasHoy = Venta::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->whereDate('fecha', today())
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM('.Venta::sqlTotalConSigno().'), 0) as total')
            ->first();

        $lineas[] = sprintf(
            'Ventas hoy: %d / $ %s',
            (int) ($ventasHoy->cantidad ?? 0),
            number_format((float) ($ventasHoy->total ?? 0), 2, ',', '.')
        );

        $stockBajo = Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->whereRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) <= productos.stock_minimo')
            ->count();
        $lineas[] = 'Productos stock bajo: '.$stockBajo;

        $morph = (new Cliente)->getMorphClass();
        $morosos = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->addSelect([
                'saldo' => DB::table('movimientos_cuenta')
                    ->selectRaw('COALESCE(SUM(importe), 0)')
                    ->whereColumn('titular_id', 'clientes.id')
                    ->where('titular_type', $morph),
            ])
            ->having('saldo', '>', 0)
            ->orderByDesc('saldo')
            ->limit(5)
            ->get(['id', 'nombre']);

        if ($morosos->isNotEmpty()) {
            $lineas[] = 'Top morosos:';
            foreach ($morosos as $c) {
                $lineas[] = '• '.$c->nombre.' $ '.number_format((float) $c->saldo, 2, ',', '.');
            }
        }

        $cajasAbiertas = CajaSesion::query()
            ->where('estado', 'abierta')
            ->whereHas('caja.sucursal', fn ($q) => $q->where('empresa_id', $empresaId))
            ->count();
        $lineas[] = 'Cajas abiertas: '.$cajasAbiertas;

        if (method_exists($this->informes, 'gestionPedidos') && auth()->check()
            && (int) auth()->user()->empresa_id === $empresaId) {
            try {
                $pedidos = $this->informes->gestionPedidos(30, 30);
                $top = collect($pedidos['items'] ?? [])
                    ->filter(fn ($i) => ($i->cantidad_sugerida ?? 0) > 0)
                    ->take(5);
                if ($top->isNotEmpty()) {
                    $lineas[] = 'Qué pedir (top 5):';
                    foreach ($top as $item) {
                        $lineas[] = sprintf(
                            '• %s x %.2f (cob %s d)',
                            $item->nombre,
                            (float) $item->cantidad_sugerida,
                            $item->dias_cobertura
                        );
                    }
                }
            } catch (\Throwable) {
                // sin contexto de auth/pedidos
            }
        }

        $texto = implode("\n", $lineas);
        if (mb_strlen($texto) > 2000) {
            $texto = mb_substr($texto, 0, 1997).'...';
        }

        return $texto;
    }
}
