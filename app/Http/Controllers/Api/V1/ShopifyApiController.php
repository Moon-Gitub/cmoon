<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\Shopify\ImportProductsFromShopify;
use App\Jobs\Shopify\SyncAllProductsToShopify;
use App\Models\ShopifyIntegracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifyApiController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $integracion = ShopifyIntegracion::where('empresa_id', $request->user()->empresa_id)->first();

        if (! $integracion) {
            return response()->json([
                'conectado' => false,
                'message' => 'Sin integración Shopify para esta empresa.',
            ]);
        }

        return response()->json([
            'conectado' => (bool) $integracion->activo,
            'store_domain' => $integracion->store_domain,
            'store_name' => $integracion->store_name,
            'sync_products' => $integracion->sync_products,
            'sync_orders' => $integracion->sync_orders,
            'auto_create_products' => $integracion->auto_create_products,
            'push_products' => $integracion->push_products,
            'last_product_sync_at' => $integracion->last_product_sync_at,
            'last_order_sync_at' => $integracion->last_order_sync_at,
            'productos_mapeados' => $integracion->productMaps()->count(),
        ]);
    }

    public function syncProductos(Request $request): JsonResponse
    {
        $integracion = ShopifyIntegracion::where('empresa_id', $request->user()->empresa_id)
            ->where('activo', true)
            ->firstOrFail();

        $direccion = $request->string('direccion', 'pull')->toString();

        if ($direccion === 'push') {
            if (! $integracion->push_products) {
                return response()->json(['message' => 'Push deshabilitado en la integración.'], 422);
            }
            SyncAllProductsToShopify::dispatch($integracion);
            $mensaje = 'Push de productos a Shopify encolado.';
        } else {
            if (! $integracion->auto_create_products) {
                return response()->json(['message' => 'Importación deshabilitada (auto_create_products=false).'], 422);
            }
            ImportProductsFromShopify::dispatch($integracion);
            $mensaje = 'Importación de productos desde Shopify encolada.';
        }

        return response()->json([
            'message' => $mensaje,
            'direccion' => $direccion,
        ], 202);
    }
}
