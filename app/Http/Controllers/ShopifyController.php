<?php

namespace App\Http\Controllers;

use App\Jobs\Shopify\ImportProductsFromShopify;
use App\Jobs\Shopify\SyncAllProductsToShopify;
use App\Models\ShopifyIntegracion;
use App\Models\ShopifyLog;
use App\Models\Sucursal;
use App\Services\ShopifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopifyController extends Controller
{
    public function index(): View
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = ShopifyIntegracion::where('empresa_id', $empresaId)->first();
        $sucursales = Sucursal::where('empresa_id', $empresaId)->where('activa', true)->get();
        $logs = collect();
        $stats = null;

        if ($integracion) {
            $stats = [
                'productos_vinculados' => $integracion->productMaps()->count(),
                'last_product_sync' => $integracion->last_product_sync_at,
                'last_order_sync' => $integracion->last_order_sync_at,
                'ventas_shopify' => \App\Models\Venta::where('empresa_id', $empresaId)
                    ->where('origen', 'shopify')
                    ->count(),
                'errores_24h' => $integracion->logs()
                    ->where('status', 'error')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ];

            $logs = $integracion->logs()->orderByDesc('created_at')->limit(20)->get();
        }

        $envConfigured = filled(config('shopify.api_key'))
            || filled(config('shopify.access_token'))
            || filled(config('shopify.store_domain'));

        return view('shopify.index', compact(
            'integracion',
            'sucursales',
            'stats',
            'logs',
            'envConfigured',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $data = $request->validate([
            'store_domain' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:20'],
            'default_sucursal_id' => ['nullable', 'exists:sucursales,id'],
        ]);

        if (! empty($data['default_sucursal_id'])) {
            $ok = Sucursal::where('id', $data['default_sucursal_id'])
                ->where('empresa_id', $empresaId)
                ->exists();
            if (! $ok) {
                return back()->with('error', 'Sucursal inválida.');
            }
        }

        $domain = $this->normalizeDomain($data['store_domain']);

        $integracion = ShopifyIntegracion::updateOrCreate(
            ['empresa_id' => $empresaId, 'store_domain' => $domain],
            [
                'access_token' => $data['access_token'],
                'api_key' => $data['api_key'] ?? config('shopify.api_key'),
                'api_secret' => $data['api_secret'] ?? config('shopify.api_secret'),
                'webhook_secret' => $data['webhook_secret'] ?? config('shopify.webhook_secret'),
                'api_version' => $data['api_version'] ?? config('shopify.api_version'),
                'default_sucursal_id' => $data['default_sucursal_id'] ?? null,
                'activo' => true,
            ]
        );

        $shop = ShopifyService::make()->forIntegracion($integracion)->getShop();
        if ($shop) {
            $integracion->update([
                'store_name' => $shop['name'] ?? $integracion->store_name,
            ]);
        }

        try {
            ShopifyService::make()
                ->forIntegracion($integracion)
                ->registerDefaultWebhooks(route('shopify.webhook'));
        } catch (\Throwable $e) {
            report($e);
        }

        ShopifyLog::registrar(
            $integracion,
            'auth',
            'pull',
            mensaje: 'Integración Shopify guardada: '.($integracion->store_name ?? $domain),
        );

        return redirect()->route('shopify.index')
            ->with('ok', 'Credenciales Shopify guardadas.');
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = ShopifyIntegracion::where('empresa_id', $empresaId)->firstOrFail();

        $data = $request->validate([
            'default_sucursal_id' => ['nullable', 'exists:sucursales,id'],
            'api_version' => ['nullable', 'string', 'max:20'],
        ]);

        if (! empty($data['default_sucursal_id'])) {
            $ok = Sucursal::where('id', $data['default_sucursal_id'])
                ->where('empresa_id', $empresaId)
                ->exists();
            if (! $ok) {
                return back()->with('error', 'Sucursal inválida.');
            }
        }

        $integracion->update([
            'sync_products' => $request->boolean('sync_products'),
            'sync_orders' => $request->boolean('sync_orders'),
            'auto_create_products' => $request->boolean('auto_create_products'),
            'push_products' => $request->boolean('push_products'),
            'default_sucursal_id' => $data['default_sucursal_id'] ?? null,
            'api_version' => $data['api_version'] ?? $integracion->api_version,
        ]);

        return back()->with('ok', 'Configuración actualizada.');
    }

    public function disconnect(): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = ShopifyIntegracion::where('empresa_id', $empresaId)->first();

        if ($integracion) {
            try {
                $service = ShopifyService::make()->forIntegracion($integracion);
                foreach ($service->listWebhooks() as $webhook) {
                    if (! empty($webhook['id'])) {
                        $service->deleteWebhook((int) $webhook['id']);
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            $integracion->delete();
        }

        return redirect()->route('shopify.index')->with('ok', 'Integración Shopify desconectada.');
    }

    public function testConnection(): RedirectResponse
    {
        $integracion = ShopifyIntegracion::where('empresa_id', auth()->user()->empresa_id)->firstOrFail();
        $shop = ShopifyService::make()->forIntegracion($integracion)->getShop();

        if (! $shop) {
            return back()->with('error', 'No se pudo conectar con Shopify. Revisá dominio y access token.');
        }

        $integracion->update(['store_name' => $shop['name'] ?? $integracion->store_name]);

        return back()->with('ok', 'Conexión OK: '.($shop['name'] ?? $integracion->store_domain));
    }

    public function importProducts(): RedirectResponse
    {
        $integracion = ShopifyIntegracion::where('empresa_id', auth()->user()->empresa_id)->firstOrFail();
        ImportProductsFromShopify::dispatch($integracion);

        return back()->with('ok', 'Importación de productos encolada.');
    }

    public function syncProducts(): RedirectResponse
    {
        $integracion = ShopifyIntegracion::where('empresa_id', auth()->user()->empresa_id)->firstOrFail();

        if (! $integracion->push_products) {
            return back()->with('error', 'Activá “Push productos” en la configuración.');
        }

        SyncAllProductsToShopify::dispatch($integracion);

        return back()->with('ok', 'Push de productos a Shopify encolado.');
    }

    public function logs(): View
    {
        $integracion = ShopifyIntegracion::where('empresa_id', auth()->user()->empresa_id)->firstOrFail();
        $logs = $integracion->logs()->orderByDesc('created_at')->paginate(50);

        return view('shopify.logs', compact('integracion', 'logs'));
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        if (! str_contains($domain, '.')) {
            $domain .= '.myshopify.com';
        }

        return $domain;
    }
}
