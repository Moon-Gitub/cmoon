<?php

namespace App\Services\Mobile;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\EntregaFoto;
use App\Models\MovimientoCuenta;
use App\Models\Presupuesto;
use App\Models\Ruta;
use App\Models\User;
use App\Models\Venta;
use App\Models\Visita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobileFieldService
{
    public function clientesQuery(User $user)
    {
        $query = Cliente::query()->where('activo', true);

        if (! $user->hasRole('Administrador')) {
            $query->where(function ($q) use ($user) {
                $q->where('vendedor_id', $user->id)->orWhereNull('vendedor_id');
            });
        }

        return $query;
    }

    public function serializarCliente(Cliente $cliente): array
    {
        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'documento' => $cliente->documento,
            'telefono' => $cliente->telefono,
            'domicilio' => $cliente->domicilio,
            'localidad' => $cliente->localidad,
            'lista_precio_id' => $cliente->lista_precio_id,
            'limite_credito' => (float) ($cliente->limite_credito ?? 0),
            'saldo' => round($cliente->saldoCuenta(), 2),
            'lat' => $cliente->lat ? (float) $cliente->lat : null,
            'lng' => $cliente->lng ? (float) $cliente->lng : null,
        ];
    }

    public function detalleCliente(Cliente $cliente): array
    {
        $ventas = Venta::where('cliente_id', $cliente->id)
            ->where('estado', 'completada')
            ->orderByDesc('fecha')
            ->limit(8)
            ->get(['id', 'numero', 'total', 'fecha']);

        $presupuestos = Presupuesto::where('cliente_id', $cliente->id)
            ->orderByDesc('fecha')
            ->limit(8)
            ->get(['id', 'numero', 'total', 'estado', 'fecha']);

        return [
            ...$this->serializarCliente($cliente),
            'ventas_recientes' => $ventas->map(fn ($v) => [
                'id' => $v->id,
                'numero' => $v->numero,
                'total' => (float) $v->total,
                'fecha' => $v->fecha->toIso8601String(),
            ]),
            'presupuestos_recientes' => $presupuestos->map(fn ($p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'total' => (float) $p->total,
                'estado' => $p->estado,
                'fecha' => $p->fecha->toDateString(),
            ]),
        ];
    }

    public function registrarCobranza(User $user, array $datos): MovimientoCuenta
    {
        $cliente = Cliente::findOrFail($datos['cliente_id']);
        abort_unless($this->clientesQuery($user)->where('id', $cliente->id)->exists(), 403);

        return MovimientoCuenta::create([
            'uuid' => $datos['uuid'],
            'titular_type' => $cliente->getMorphClass(),
            'titular_id' => $cliente->id,
            'tipo' => 'recibo',
            'concepto' => $datos['concepto'] ?? 'Cobranza móvil',
            'importe' => -abs((float) $datos['importe']),
            'user_id' => $user->id,
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
        ]);
    }

    public function registrarVisita(User $user, array $datos): Visita
    {
        abort_unless($this->clientesQuery($user)->where('id', $datos['cliente_id'])->exists(), 403);

        return Visita::create([
            'uuid' => $datos['uuid'],
            'empresa_id' => $user->empresa_id,
            'cliente_id' => $datos['cliente_id'],
            'user_id' => $user->id,
            'ruta_id' => $datos['ruta_id'] ?? null,
            'estado' => $datos['estado'] ?? 'visitada',
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'lat' => $datos['lat'] ?? null,
            'lng' => $datos['lng'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'checkin_at' => now(),
        ]);
    }

    public function registrarEntrega(User $user, array $datos): Entrega
    {
        abort_unless($this->clientesQuery($user)->where('id', $datos['cliente_id'])->exists(), 403);

        return DB::transaction(function () use ($user, $datos) {
            $firmaPath = null;
            if (! empty($datos['firma_base64'])) {
                $firmaPath = $this->guardarImagenBase64($datos['firma_base64'], 'entregas/firmas');
            }

            $entrega = Entrega::create([
                'uuid' => $datos['uuid'],
                'empresa_id' => $user->empresa_id,
                'cliente_id' => $datos['cliente_id'],
                'user_id' => $user->id,
                'presupuesto_id' => $datos['presupuesto_id'] ?? null,
                'venta_id' => $datos['venta_id'] ?? null,
                'estado' => $datos['estado'] ?? 'entregada',
                'observaciones' => $datos['observaciones'] ?? null,
                'firma_path' => $firmaPath,
                'entregado_at' => now(),
            ]);

            foreach ($datos['fotos_base64'] ?? [] as $foto) {
                if (! $foto) {
                    continue;
                }
                EntregaFoto::create([
                    'entrega_id' => $entrega->id,
                    'path' => $this->guardarImagenBase64($foto, 'entregas/fotos'),
                ]);
            }

            return $entrega->load('fotos');
        });
    }

    public function rutasDelDia(User $user): array
    {
        $dia = (int) now()->dayOfWeek; // 0=domingo

        $rutas = Ruta::with(['clientes' => fn ($q) => $q->where('activo', true)])
            ->where('user_id', $user->id)
            ->where('activa', true)
            ->where(fn ($q) => $q->whereNull('dia_semana')->orWhere('dia_semana', $dia))
            ->get();

        $visitadosHoy = Visita::where('user_id', $user->id)
            ->whereDate('fecha', today())
            ->pluck('cliente_id')
            ->flip();

        $clientes = collect();

        foreach ($rutas as $ruta) {
            foreach ($ruta->clientes as $cliente) {
                $clientes->push([
                    'ruta_id' => $ruta->id,
                    'ruta_nombre' => $ruta->nombre,
                    'orden' => (int) $cliente->pivot->orden,
                    'visitado_hoy' => $visitadosHoy->has($cliente->id),
                    ...$this->serializarCliente($cliente),
                ]);
            }
        }

        if ($clientes->isEmpty()) {
            return $this->clientesQuery($user)
                ->orderBy('nombre')
                ->get()
                ->map(fn ($c) => [
                    'ruta_id' => null,
                    'ruta_nombre' => 'Cartera',
                    'orden' => 0,
                    'visitado_hoy' => $visitadosHoy->has($c->id),
                    ...$this->serializarCliente($c),
                ])
                ->values()
                ->all();
        }

        return $clientes->sortBy(['ruta_nombre', 'orden', 'nombre'])->values()->all();
    }

    public function entregasPendientes(User $user): array
    {
        $clienteIds = $this->clientesQuery($user)->pluck('id');

        $presupuestos = Presupuesto::with('cliente')
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', 'aprobado')
            ->whereDoesntHave('entregas')
            ->orderByDesc('fecha')
            ->limit(50)
            ->get();

        $ventas = Venta::with('cliente')
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', 'completada')
            ->whereDoesntHave('entregas')
            ->orderByDesc('fecha')
            ->limit(50)
            ->get();

        $items = collect();

        foreach ($presupuestos as $p) {
            $items->push([
                'tipo' => 'presupuesto',
                'presupuesto_id' => $p->id,
                'venta_id' => null,
                'numero' => $p->numero,
                'cliente_id' => $p->cliente_id,
                'cliente_nombre' => $p->cliente?->nombre,
                'total' => (float) $p->total,
                'fecha' => $p->fecha->toDateString(),
            ]);
        }

        foreach ($ventas as $v) {
            $items->push([
                'tipo' => 'venta',
                'presupuesto_id' => null,
                'venta_id' => $v->id,
                'numero' => $v->numero,
                'cliente_id' => $v->cliente_id,
                'cliente_nombre' => $v->cliente?->nombre,
                'total' => (float) $v->total,
                'fecha' => $v->fecha->toIso8601String(),
            ]);
        }

        return $items->sortByDesc('fecha')->values()->all();
    }

    public function reporteVendedor(User $user, ?string $desde = null, ?string $hasta = null): array
    {
        $desdeDt = $desde ? now()->parse($desde)->startOfDay() : now()->startOfMonth();
        $hastaDt = $hasta ? now()->parse($hasta)->endOfDay() : now()->endOfDay();

        $pedidos = Presupuesto::where('user_id', $user->id)
            ->whereBetween('fecha', [$desdeDt->toDateString(), $hastaDt->toDateString()])
            ->get();

        $ventas = Venta::where('user_id', $user->id)
            ->where('estado', 'completada')
            ->whereBetween('fecha', [$desdeDt, $hastaDt])
            ->get();

        $cobranzas = MovimientoCuenta::where('user_id', $user->id)
            ->where('tipo', 'recibo')
            ->whereBetween('fecha', [$desdeDt->toDateString(), $hastaDt->toDateString()])
            ->get();

        $visitas = Visita::where('user_id', $user->id)
            ->whereBetween('fecha', [$desdeDt->toDateString(), $hastaDt->toDateString()])
            ->get();

        $entregas = Entrega::where('user_id', $user->id)
            ->whereBetween('entregado_at', [$desdeDt, $hastaDt])
            ->get();

        return [
            'desde' => $desdeDt->toDateString(),
            'hasta' => $hastaDt->toDateString(),
            'pedidos' => [
                'cantidad' => $pedidos->count(),
                'total' => round((float) $pedidos->sum('total'), 2),
                'aprobados' => $pedidos->whereIn('estado', ['aprobado', 'convertido'])->count(),
            ],
            'ventas' => [
                'cantidad' => $ventas->count(),
                'total' => round((float) $ventas->sum('total'), 2),
            ],
            'cobranzas' => [
                'cantidad' => $cobranzas->count(),
                'total' => round(abs((float) $cobranzas->sum('importe')), 2),
            ],
            'visitas' => [
                'cantidad' => $visitas->count(),
                'clientes_unicos' => $visitas->pluck('cliente_id')->unique()->count(),
            ],
            'entregas' => [
                'cantidad' => $entregas->count(),
            ],
        ];
    }

    private function guardarImagenBase64(string $dataUrl, string $carpeta): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $m)) {
            $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $data = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        } else {
            $ext = 'png';
            $data = base64_decode($dataUrl);
        }

        $path = $carpeta.'/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $data);

        return $path;
    }
}
