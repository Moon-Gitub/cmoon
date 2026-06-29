<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Services\TiendanubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TiendanubeController extends Controller
{
    public function __construct(
        private readonly TiendanubeService $tiendanube,
    ) {}

    /**
     * Panel principal de integración Tiendanube
     */
    public function index(): View
    {
        $empresaId = auth()->user()->empresa_id;

        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->first();
        $sucursales = Sucursal::where('empresa_id', $empresaId)->where('activa', true)->get();

        $stats = null;
        $logs = collect();

        if ($integracion) {
            $stats = [
                'productos_vinculados' => $integracion->productMaps()->count(),
                'categorias_vinculadas' => $integracion->categoryMaps()->count(),
                'last_product_sync' => $integracion->last_product_sync_at,
                'last_stock_sync' => $integracion->last_stock_sync_at,
                'last_order_sync' => $integracion->last_order_sync_at,
            ];

            $logs = $integracion->logs()
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        $configured = filled(config('tiendanube.client_id'));

        return view('tiendanube.index', compact(
            'integracion',
            'sucursales',
            'stats',
            'logs',
            'configured',
        ));
    }

    /**
     * Inicia el flujo OAuth redirigiendo a Tiendanube
     */
    public function connect(): RedirectResponse
    {
        if (blank(config('tiendanube.client_id'))) {
            return back()->with('error', 'Tiendanube no está configurado. Agregá TIENDANUBE_CLIENT_ID y TIENDANUBE_CLIENT_SECRET en .env');
        }

        $state = Str::random(40);
        session(['tiendanube_oauth_state' => $state]);

        $url = $this->tiendanube->getAuthorizationUrl($state);

        return redirect()->away($url);
    }

    /**
     * Callback de OAuth después de autorizar en Tiendanube
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');

        if (! $code) {
            return redirect()->route('tiendanube.index')
                ->with('error', 'No se recibió código de autorización de Tiendanube.');
        }

        if ($state !== session('tiendanube_oauth_state')) {
            return redirect()->route('tiendanube.index')
                ->with('error', 'Estado de seguridad inválido. Intentá conectar de nuevo.');
        }

        session()->forget('tiendanube_oauth_state');

        try {
            $tokenData = $this->tiendanube->exchangeCodeForToken($code);

            $empresaId = auth()->user()->empresa_id;
            $storeId = $tokenData['user_id'];
            $accessToken = $tokenData['access_token'];
            $scopes = explode(',', $tokenData['scope'] ?? '');

            // Obtener info de la tienda
            $integracion = TiendanubeIntegracion::updateOrCreate(
                ['empresa_id' => $empresaId, 'store_id' => $storeId],
                [
                    'access_token' => $accessToken,
                    'scopes' => $scopes,
                    'activo' => true,
                ]
            );

            // Obtener datos de la tienda
            $storeInfo = $this->tiendanube->forIntegracion($integracion)->getStore();

            if ($storeInfo) {
                $integracion->update([
                    'store_name' => $storeInfo['name']['es'] ?? $storeInfo['name']['en'] ?? 'Tienda',
                    'store_url' => $storeInfo['url_with_protocol'] ?? $storeInfo['original_domain'] ?? null,
                ]);
            }

            // Generar secret para webhooks
            $integracion->generateWebhookSecret();

            // Registrar webhooks
            $webhookUrl = route('tiendanube.webhook');
            $this->tiendanube->forIntegracion($integracion)->registerAllWebhooks($webhookUrl);

            TiendanubeLog::registrar(
                $integracion,
                'auth',
                'pull',
                mensaje: 'Tienda conectada exitosamente: '.($integracion->store_name ?? $storeId),
            );

            return redirect()->route('tiendanube.index')
                ->with('ok', '¡Tienda Tiendanube conectada exitosamente!');

        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('tiendanube.index')
                ->with('error', 'Error al conectar con Tiendanube: '.$e->getMessage());
        }
    }

    /**
     * Desconectar integración
     */
    public function disconnect(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->first();

        if ($integracion) {
            // Intentar eliminar webhooks en Tiendanube
            try {
                $webhooks = $this->tiendanube->forIntegracion($integracion)->getWebhooks();
                foreach ($webhooks as $webhook) {
                    $this->tiendanube->deleteWebhook($webhook['id']);
                }
            } catch (\Throwable) {
                // Ignorar errores al eliminar webhooks
            }

            $integracion->delete();
        }

        return redirect()->route('tiendanube.index')
            ->with('ok', 'Integración con Tiendanube desconectada.');
    }

    /**
     * Actualizar configuración de sincronización
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        $data = $request->validate([
            'sync_products' => ['boolean'],
            'sync_stock' => ['boolean'],
            'sync_orders' => ['boolean'],
            'sync_customers' => ['boolean'],
            'auto_create_products' => ['boolean'],
            'default_sucursal_id' => ['nullable', 'exists:sucursales,id'],
        ]);

        // Asegurar que la sucursal pertenece a la empresa
        if (! empty($data['default_sucursal_id'])) {
            $sucursal = Sucursal::find($data['default_sucursal_id']);
            if (! $sucursal || $sucursal->empresa_id !== $empresaId) {
                return back()->with('error', 'Sucursal inválida.');
            }
        }

        $integracion->update([
            'sync_products' => $request->boolean('sync_products'),
            'sync_stock' => $request->boolean('sync_stock'),
            'sync_orders' => $request->boolean('sync_orders'),
            'sync_customers' => $request->boolean('sync_customers'),
            'auto_create_products' => $request->boolean('auto_create_products'),
            'default_sucursal_id' => $data['default_sucursal_id'] ?? null,
        ]);

        return back()->with('ok', 'Configuración actualizada.');
    }

    /**
     * Probar conexión con Tiendanube
     */
    public function testConnection(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        $ok = $this->tiendanube->forIntegracion($integracion)->testConnection();

        if ($ok) {
            return back()->with('ok', 'Conexión exitosa con Tiendanube.');
        }

        return back()->with('error', 'No se pudo conectar con Tiendanube. Verificá que la tienda siga activa.');
    }

    /**
     * Ver logs de sincronización
     */
    public function logs(Request $request): View
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        $tipo = $request->query('tipo');

        $query = $integracion->logs()->orderByDesc('created_at');

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('tiendanube.logs', compact('integracion', 'logs', 'tipo'));
    }

    /**
     * Ver productos vinculados
     */
    public function productos(Request $request): View
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        $productMaps = $integracion->productMaps()
            ->with('producto')
            ->orderByDesc('last_synced_at')
            ->paginate(50);

        return view('tiendanube.productos', compact('integracion', 'productMaps'));
    }

    /**
     * Sincronizar todos los productos a Tiendanube
     */
    public function syncProducts(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        if (! $integracion->sync_products) {
            return back()->with('error', 'La sincronización de productos está desactivada.');
        }

        // Dispatch job para sincronización en background
        \App\Jobs\Tiendanube\SyncAllProductsToTiendanube::dispatch($integracion);

        return back()->with('ok', 'Sincronización de productos iniciada. Revisá los logs para ver el progreso.');
    }

    /**
     * Sincronizar todo el stock a Tiendanube
     */
    public function syncStock(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        if (! $integracion->sync_stock) {
            return back()->with('error', 'La sincronización de stock está desactivada.');
        }

        if (! $integracion->default_sucursal_id) {
            return back()->with('error', 'Configurá una sucursal por defecto primero.');
        }

        \App\Jobs\Tiendanube\SyncAllStockToTiendanube::dispatch($integracion);

        return back()->with('ok', 'Sincronización de stock iniciada.');
    }

    /**
     * Importar órdenes recientes de Tiendanube
     */
    public function importOrders(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        if (! $integracion->sync_orders) {
            return back()->with('error', 'La importación de órdenes está desactivada.');
        }

        \App\Jobs\Tiendanube\ImportRecentOrders::dispatch($integracion);

        return back()->with('ok', 'Importación de órdenes iniciada.');
    }

    /**
     * Importar productos de Tiendanube a POSMoon
     */
    public function importProducts(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = TiendanubeIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        if (! $integracion->auto_create_products) {
            return back()->with('error', 'La creación automática de productos está desactivada.');
        }

        \App\Jobs\Tiendanube\ImportProductsFromTiendanube::dispatch($integracion);

        return back()->with('ok', 'Importación de productos iniciada.');
    }
}
