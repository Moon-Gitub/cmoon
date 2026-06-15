<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\DesktopInstallation;
use App\Models\ListaPrecio;
use App\Models\MedioPago;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\Desktop\DesktopLicenseService;
use App\Services\PresupuestoService;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DesktopApiController extends Controller
{
    public function __construct(
        private DesktopLicenseService $licencias,
        private VentaService $ventas,
        private PresupuestoService $presupuestos,
    ) {}

    /**
     * Activación inicial: vincula dispositivo + usuario + moon_client_id.
     */
    public function activate(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'uuid'],
            'device_name' => ['required', 'string', 'max:100'],
            'moon_client_id' => ['required', 'integer', 'min:1'],
        ]);

        $user = User::where('usuario', $datos['usuario'])
            ->orWhere('email', $datos['usuario'])
            ->first();

        if (! $user || ! Hash::check($datos['password'], $user->password) || ! $user->activo) {
            throw ValidationException::withMessages(['usuario' => 'Credenciales inválidas.']);
        }

        $canSell = $user->can('pos.vender');
        $canPedidos = $user->can('presupuestos.crear_movil');

        if (! $canSell && ! $canPedidos) {
            throw ValidationException::withMessages(['usuario' => 'Este usuario no puede operar la app móvil.']);
        }

        $user->load('empresa');

        $tokenPlano = $this->licencias->crearTokenDispositivo();

        $instalacion = DesktopInstallation::updateOrCreate(
            ['device_id' => $datos['device_id']],
            [
                'empresa_id' => $user->empresa_id,
                'user_id' => $user->id,
                'moon_client_id' => $datos['moon_client_id'],
                'device_name' => $datos['device_name'],
                'token_hash' => $this->licencias->hashToken($tokenPlano),
                'activa' => true,
                'last_seen_at' => now(),
            ]
        );

        $licencia = $this->licencias->emitir($instalacion, $tokenPlano);

        auth()->login($user);

        return response()->json([
            'device_token' => $tokenPlano,
            'device_id' => $instalacion->device_id,
            'moon_client_id' => $instalacion->moon_client_id,
            'empresa' => [
                'id' => $user->empresa_id,
                'nombre' => $user->empresa?->nombre_fantasia ?? $user->empresa?->razon_social,
            ],
            'sucursal_id' => $user->sucursal_id ?? Sucursal::where('empresa_id', $user->empresa_id)->where('activa', true)->value('id'),
            'usuario' => $user->only(['id', 'name', 'usuario']),
            'capabilities' => [
                'can_sell' => $canSell,
                'can_pedidos' => $canPedidos,
            ],
            ...$licencia,
            'catalog' => $this->armarCatalogo(),
        ]);
    }

    /** Renovar licencia (requiere internet). */
    public function license(Request $request): JsonResponse
    {
        /** @var DesktopInstallation $instalacion */
        $instalacion = $request->attributes->get('desktop_installation');

        return response()->json($this->licencias->emitir(
            $instalacion,
            $request->attributes->get('desktop_token'),
        ));
    }

    /** Catálogo completo para cache local. */
    public function catalog(Request $request): JsonResponse
    {
        $request->attributes->get('desktop_installation')->update(['last_sync_at' => now()]);

        return response()->json($this->armarCatalogo());
    }

    /** Sincronizar ventas hechas offline en la caja. */
    public function syncVentas(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('pos.vender'), 403);

        /** @var DesktopInstallation $instalacion */
        $instalacion = $request->attributes->get('desktop_installation');
        $deviceToken = $request->attributes->get('desktop_token');

        $datos = $request->validate([
            'ventas' => ['required', 'array'],
            'ventas.*.uuid' => ['required', 'uuid'],
            'ventas.*.sucursal_id' => ['required', 'integer'],
            'ventas.*.items' => ['required', 'array', 'min:1'],
            'ventas.*.pagos' => ['required', 'array', 'min:1'],
        ]);

        $resultados = [];

        foreach ($datos['ventas'] as $ventaDatos) {
            $existente = Venta::where('uuid', $ventaDatos['uuid'])->first();
            if ($existente) {
                $resultados[] = ['uuid' => $ventaDatos['uuid'], 'ok' => true, 'id' => $existente->id, 'numero' => $existente->numero];

                continue;
            }

            try {
                $venta = $this->ventas->crear([
                    ...$ventaDatos,
                    'origen' => 'desktop',
                    'fecha' => $ventaDatos['fecha'] ?? now()->toIso8601String(),
                ], auth()->id());

                $resultados[] = ['uuid' => $ventaDatos['uuid'], 'ok' => true, 'id' => $venta->id, 'numero' => $venta->numero];
            } catch (\Throwable $e) {
                $resultados[] = ['uuid' => $ventaDatos['uuid'], 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        $instalacion->update(['last_sync_at' => now()]);

        return response()->json([
            'resultados' => $resultados,
            'license' => $this->licencias->emitir($instalacion, $deviceToken)['license'],
        ]);
    }

    /** Sincronizar pedidos del preventista (presupuestos pendientes de aprobación). */
    public function syncPedidos(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('presupuestos.crear_movil'), 403);

        /** @var DesktopInstallation $instalacion */
        $instalacion = $request->attributes->get('desktop_installation');
        $deviceToken = $request->attributes->get('desktop_token');

        $datos = $request->validate([
            'pedidos' => ['required', 'array'],
            'pedidos.*.uuid' => ['required', 'uuid'],
            'pedidos.*.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'pedidos.*.items' => ['required', 'array', 'min:1'],
            'pedidos.*.items.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'pedidos.*.items.*.descripcion' => ['required', 'string', 'max:255'],
            'pedidos.*.items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'pedidos.*.items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'pedidos.*.observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $resultados = [];

        foreach ($datos['pedidos'] as $pedidoDatos) {
            $existente = Presupuesto::where('uuid', $pedidoDatos['uuid'])->first();
            if ($existente) {
                $resultados[] = [
                    'uuid' => $pedidoDatos['uuid'],
                    'ok' => true,
                    'id' => $existente->id,
                    'numero' => $existente->numero,
                    'estado' => $existente->estado,
                ];

                continue;
            }

            try {
                $presupuesto = $this->presupuestos->crear(
                    $instalacion->empresa_id,
                    auth()->id(),
                    $pedidoDatos['items'],
                    (int) $pedidoDatos['cliente_id'],
                    $pedidoDatos['observaciones'] ?? null,
                    null,
                    'pendiente_aprobacion',
                    'movil',
                    $pedidoDatos['uuid'],
                );

                $resultados[] = [
                    'uuid' => $pedidoDatos['uuid'],
                    'ok' => true,
                    'id' => $presupuesto->id,
                    'numero' => $presupuesto->numero,
                    'estado' => $presupuesto->estado,
                ];
            } catch (\Throwable $e) {
                $resultados[] = ['uuid' => $pedidoDatos['uuid'], 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        $instalacion->update(['last_sync_at' => now()]);

        return response()->json([
            'resultados' => $resultados,
            'license' => $this->licencias->emitir($instalacion, $deviceToken)['license'],
        ]);
    }

    public function estado(Request $request): JsonResponse
    {
        /** @var DesktopInstallation $instalacion */
        $instalacion = $request->attributes->get('desktop_installation');

        return response()->json([
            'device' => $instalacion->only(['device_id', 'device_name', 'moon_client_id', 'last_seen_at', 'last_sync_at']),
            ...$this->licencias->emitir($instalacion, $request->attributes->get('desktop_token')),
        ]);
    }

    private function armarCatalogo(): array
    {
        return [
            'productos' => Producto::where('activo', true)
                ->get(['id', 'codigo', 'nombre', 'precio_venta', 'alicuota_iva', 'unidad', 'pesable', 'es_combo'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio_venta,
                    'iva' => (float) $p->alicuota_iva,
                    'unidad' => $p->unidad,
                    'pesable' => (bool) $p->pesable,
                ]),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'documento', 'lista_precio_id']),
            'listas' => ListaPrecio::where('activa', true)->get(['id', 'nombre', 'porcentaje'])
                ->map(fn ($l) => ['id' => $l->id, 'nombre' => $l->nombre, 'porcentaje' => (float) $l->porcentaje]),
            'medios' => MedioPago::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo', 'recargo_porcentaje'])
                ->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo' => $m->tipo, 'recargo' => (float) $m->recargo_porcentaje]),
            'synced_at' => now()->toIso8601String(),
        ];
    }
}
