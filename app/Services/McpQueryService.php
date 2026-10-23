<?php

namespace App\Services;

use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class McpQueryService
{
    public function empresaId(User $user): int
    {
        abort_unless((bool) $user->empresa_id, 403, 'El usuario no tiene empresa asignada.');

        return (int) $user->empresa_id;
    }

    public function resumen(User $user): array
    {
        $empresaId = $this->empresaId($user);
        $empresa = Empresa::query()
            ->where('id', $empresaId)
            ->firstOrFail(['id', 'razon_social', 'nombre_fantasia', 'cuit', 'localidad', 'provincia', 'activa']);

        $hoy = Venta::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->whereDate('fecha', today())
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM('.Venta::sqlTotalConSigno().'), 0) as total')
            ->first();

        $mes = Venta::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM('.Venta::sqlTotalConSigno().'), 0) as total')
            ->first();

        $stockBajo = Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->whereRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) <= productos.stock_minimo')
            ->count();

        $cajasAbiertas = CajaSesion::query()
            ->where('estado', 'abierta')
            ->whereHas('caja.sucursal', fn ($q) => $q->where('empresa_id', $empresaId))
            ->count();

        return [
            'empresa' => [
                'id' => $empresa->id,
                'razon_social' => $empresa->razon_social,
                'nombre_fantasia' => $empresa->nombre_fantasia,
                'cuit' => $empresa->cuit,
                'localidad' => $empresa->localidad,
                'provincia' => $empresa->provincia,
                'activa' => (bool) $empresa->activa,
            ],
            'ventas_hoy' => [
                'cantidad' => (int) ($hoy->cantidad ?? 0),
                'total' => round((float) ($hoy->total ?? 0), 2),
            ],
            'ventas_mes' => [
                'cantidad' => (int) ($mes->cantidad ?? 0),
                'total' => round((float) ($mes->total ?? 0), 2),
            ],
            'productos_stock_bajo' => $stockBajo,
            'cajas_abiertas' => $cajasAbiertas,
        ];
    }

    public function productos(User $user, ?string $q, int $limit, bool $soloActivos = true): array
    {
        $empresaId = $this->empresaId($user);
        $limit = $this->cap($limit);

        $query = Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->with(['categoria:id,nombre', 'stocks:id,producto_id,sucursal_id,cantidad']);

        if ($soloActivos) {
            $query->where('activo', true);
        }

        if ($q) {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('nombre', 'like', $like)
                    ->orWhere('codigo', 'like', $like);
            });
        }

        return $query->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'precio_venta' => (float) $p->precio_venta,
                'precio_promocional' => $p->precio_promocional !== null ? (float) $p->precio_promocional : null,
                'stock_total' => (float) $p->stocks->sum('cantidad'),
                'stock_minimo' => (float) $p->stock_minimo,
                'categoria' => $p->categoria?->nombre,
                'activo' => (bool) $p->activo,
            ])
            ->all();
    }

    public function producto(User $user, int $id): array
    {
        $p = Producto::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId($user))
            ->with(['categoria:id,nombre', 'stocks.sucursal:id,nombre'])
            ->findOrFail($id);

        return [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'unidad' => $p->unidad,
            'precio_venta' => (float) $p->precio_venta,
            'precio_compra' => (float) $p->precio_compra,
            'precio_promocional' => $p->precio_promocional !== null ? (float) $p->precio_promocional : null,
            'alicuota_iva' => (float) $p->alicuota_iva,
            'stock_minimo' => (float) $p->stock_minimo,
            'categoria' => $p->categoria?->nombre,
            'activo' => (bool) $p->activo,
            'stocks' => $p->stocks->map(fn ($s) => [
                'sucursal_id' => $s->sucursal_id,
                'sucursal' => $s->sucursal?->nombre,
                'cantidad' => (float) $s->cantidad,
            ])->all(),
        ];
    }

    public function clientes(User $user, ?string $q, int $limit): array
    {
        $empresaId = $this->empresaId($user);
        $limit = $this->cap($limit);
        $morph = (new Cliente)->getMorphClass();

        $query = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->addSelect([
                'clientes.id',
                'clientes.nombre',
                'clientes.documento',
                'clientes.email',
                'clientes.telefono',
                'clientes.localidad',
                'saldo' => DB::table('movimientos_cuenta')
                    ->selectRaw('COALESCE(SUM(importe), 0)')
                    ->whereColumn('titular_id', 'clientes.id')
                    ->where('titular_type', $morph),
            ]);

        if ($q) {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('nombre', 'like', $like)
                    ->orWhere('documento', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('telefono', 'like', $like);
            });
        }

        return $query->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'documento' => $c->documento,
                'email' => $c->email,
                'telefono' => $c->telefono,
                'localidad' => $c->localidad,
                'saldo_cta_cte' => round((float) $c->saldo, 2),
            ])
            ->all();
    }

    public function ventas(User $user, ?string $desde, ?string $hasta, ?string $estado, int $limit): array
    {
        $empresaId = $this->empresaId($user);
        $limit = $this->cap($limit);

        $query = Venta::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->with(['cliente:id,nombre', 'vendedor:id,name']);

        if ($desde) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('fecha', '<=', $hasta);
        }
        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->orderByDesc('fecha')
            ->limit($limit)
            ->get()
            ->map(fn (Venta $v) => [
                'id' => $v->id,
                'numero' => $v->numero,
                'fecha' => optional($v->fecha)->toIso8601String(),
                'estado' => $v->estado,
                'tipo' => $v->tipo,
                'total' => (float) $v->totalConSigno(),
                'cliente' => $v->cliente?->nombre,
                'vendedor' => $v->vendedor?->name,
            ])
            ->all();
    }

    public function venta(User $user, int $id): array
    {
        $v = Venta::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId($user))
            ->with([
                'items:id,venta_id,producto_id,descripcion,cantidad,precio_unitario,total',
                'pagos.medioPago:id,nombre',
                'cliente:id,nombre,documento',
                'vendedor:id,name',
            ])
            ->findOrFail($id);

        return [
            'id' => $v->id,
            'numero' => $v->numero,
            'fecha' => optional($v->fecha)->toIso8601String(),
            'estado' => $v->estado,
            'tipo' => $v->tipo,
            'origen' => $v->origen,
            'subtotal' => (float) $v->subtotal,
            'descuento' => (float) $v->descuento,
            'recargo' => (float) $v->recargo,
            'total' => (float) $v->totalConSigno(),
            'cliente' => $v->cliente ? [
                'id' => $v->cliente->id,
                'nombre' => $v->cliente->nombre,
                'documento' => $v->cliente->documento,
            ] : null,
            'vendedor' => $v->vendedor?->name,
            'items' => $v->items->map(fn ($i) => [
                'producto_id' => $i->producto_id,
                'descripcion' => $i->descripcion,
                'cantidad' => (float) $i->cantidad,
                'precio_unitario' => (float) $i->precio_unitario,
                'total' => (float) $i->total,
            ])->all(),
            'pagos' => $v->pagos->map(fn ($p) => [
                'medio' => $p->medioPago?->nombre,
                'importe' => (float) $p->importe,
            ])->all(),
        ];
    }

    public function stockBajo(User $user, int $limit): array
    {
        $empresaId = $this->empresaId($user);
        $limit = $this->cap($limit);

        return Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->with('stocks:id,producto_id,cantidad')
            ->whereRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) <= productos.stock_minimo')
            ->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'stock_total' => (float) $p->stocks->sum('cantidad'),
                'stock_minimo' => (float) $p->stock_minimo,
            ])
            ->all();
    }

    public function cajas(User $user): array
    {
        $empresaId = $this->empresaId($user);

        return CajaSesion::query()
            ->with(['caja:id,nombre,sucursal_id', 'caja.sucursal:id,nombre,empresa_id', 'usuario:id,name'])
            ->where('estado', 'abierta')
            ->whereHas('caja.sucursal', fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('abierta_at')
            ->get()
            ->map(fn (CajaSesion $s) => [
                'id' => $s->id,
                'caja' => $s->caja?->nombre,
                'sucursal' => $s->caja?->sucursal?->nombre,
                'usuario' => $s->usuario?->name,
                'monto_apertura' => (float) $s->monto_apertura,
                'abierta_at' => optional($s->abierta_at)->toIso8601String(),
            ])
            ->all();
    }

    public function cuentaCliente(User $user, int $clienteId, int $limit): array
    {
        $cliente = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId($user))
            ->findOrFail($clienteId);

        $limit = $this->cap($limit);
        $movimientos = $cliente->movimientosCuenta()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'tipo', 'concepto', 'importe', 'fecha', 'created_at']);

        return [
            'cliente' => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
            ],
            'saldo' => round($cliente->saldoCuenta(), 2),
            'movimientos' => $movimientos->map(fn ($m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'importe' => (float) $m->importe,
                'concepto' => $m->concepto,
                'fecha' => optional($m->fecha ?? $m->created_at)?->toDateString(),
            ])->all(),
        ];
    }

    private function cap(int $limit): int
    {
        return max(1, min($limit, 50));
    }
}
