<?php

namespace App\Http\Controllers;

use App\Jobs\Tiendanube\ImportTiendanubeOrder;
use App\Jobs\Tiendanube\SyncProductFromTiendanube;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TiendanubeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $storeId = $request->input('store_id');
        $event = $request->input('event');
        $entityId = $request->input('id');

        if (! $storeId || ! $event) {
            return response('Bad request', 400);
        }

        $integracion = TiendanubeIntegracion::where('store_id', $storeId)
            ->where('activo', true)
            ->first();

        if (! $integracion) {
            return response('Store not found', 404);
        }

        // Verificar firma HMAC
        $signature = $request->header('x-linkedstore-hmac-sha256');
        if ($signature) {
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, config('tiendanube.client_secret'));

            if (! hash_equals($expected, $signature)) {
                TiendanubeLog::registrar(
                    $integracion,
                    'webhook',
                    'webhook',
                    mensaje: "Firma HMAC inválida para evento {$event}",
                    status: 'error',
                );

                return response('Invalid signature', 401);
            }
        }

        // Registrar webhook recibido
        TiendanubeLog::registrar(
            $integracion,
            'webhook',
            'webhook',
            entidadTipo: $this->getEntityType($event),
            entidadId: $entityId,
            request: $request->all(),
            mensaje: "Webhook recibido: {$event}",
        );

        // Procesar evento
        $this->processEvent($integracion, $event, $entityId, $request->all());

        return response('OK', 200);
    }

    private function processEvent(
        TiendanubeIntegracion $integracion,
        string $event,
        ?int $entityId,
        array $payload,
    ): void {
        match ($event) {
            // Productos
            'product/created', 'product/updated' => $this->handleProductUpdate($integracion, $entityId),
            'product/deleted' => $this->handleProductDelete($integracion, $entityId),

            // Órdenes
            'order/created' => $this->handleOrderCreated($integracion, $entityId),
            'order/paid' => $this->handleOrderPaid($integracion, $entityId, $payload),
            'order/cancelled' => $this->handleOrderCancelled($integracion, $entityId),
            'order/fulfilled' => $this->handleOrderFulfilled($integracion, $entityId, $payload),
            'order/packed' => $this->handleOrderPacked($integracion, $entityId),

            // Carritos abandonados
            'cart/created', 'cart/updated' => $this->handleCartUpdate($integracion, $entityId, $payload),

            // Clientes
            'customer/created', 'customer/updated' => $this->handleCustomerUpdate($integracion, $entityId, $payload),

            // Categorías
            'category/created', 'category/updated' => $this->handleCategoryUpdate($integracion, $entityId),
            'category/deleted' => $this->handleCategoryDelete($integracion, $entityId),

            // App
            'app/uninstalled' => $this->handleAppUninstalled($integracion),

            default => null,
        };
    }

    private function handleProductUpdate(TiendanubeIntegracion $integracion, ?int $productId): void
    {
        if (! $productId || ! $integracion->auto_create_products) {
            return;
        }

        SyncProductFromTiendanube::dispatch($integracion, $productId);
    }

    private function handleProductDelete(TiendanubeIntegracion $integracion, ?int $productId): void
    {
        if (! $productId) {
            return;
        }

        // Desactivar producto local vinculado
        $map = $integracion->productMaps()->where('tn_product_id', $productId)->first();

        if ($map && $map->producto) {
            $map->producto->update(['activo' => false]);

            TiendanubeLog::registrar(
                $integracion,
                'product_sync',
                'webhook',
                'product',
                $map->producto_id,
                mensaje: "Producto desactivado por eliminación en Tiendanube: {$map->producto->nombre}",
            );
        }
    }

    private function handleOrderCreated(TiendanubeIntegracion $integracion, ?int $orderId): void
    {
        // Solo registrar, no importar hasta que esté pagada
        if (! $orderId || ! $integracion->sync_orders) {
            return;
        }

        TiendanubeLog::registrar(
            $integracion,
            'order_import',
            'webhook',
            'order',
            $orderId,
            mensaje: "Orden creada en Tiendanube #{$orderId}, esperando pago",
        );
    }

    private function handleOrderPaid(TiendanubeIntegracion $integracion, ?int $orderId): void
    {
        if (! $orderId || ! $integracion->sync_orders) {
            return;
        }

        ImportTiendanubeOrder::dispatch($integracion, $orderId);
    }

    private function handleOrderCancelled(TiendanubeIntegracion $integracion, ?int $orderId): void
    {
        if (! $orderId) {
            return;
        }

        // Buscar venta vinculada y anularla
        $venta = \App\Models\Venta::where('tn_order_id', $orderId)->first();

        if ($venta && ! $venta->anulada_at) {
            $venta->update([
                'anulada_at' => now(),
                'motivo_anulacion' => 'Orden cancelada en Tiendanube',
            ]);

            TiendanubeLog::registrar(
                $integracion,
                'order_import',
                'webhook',
                'order',
                $orderId,
                mensaje: "Venta #{$venta->numero} anulada por cancelación en Tiendanube",
            );
        }
    }

    private function handleOrderFulfilled(TiendanubeIntegracion $integracion, ?int $orderId, array $payload = []): void
    {
        if (! $orderId) {
            return;
        }

        // Actualizar estado local
        $venta = \App\Models\Venta::where('tn_order_id', $orderId)->first();

        if ($venta) {
            $venta->update([
                'estado_envio' => 'despachado',
                'despachada_at' => now(),
                'tracking_number' => $payload['shipping_tracking_number'] ?? $venta->tracking_number,
            ]);
        }

        TiendanubeLog::registrar(
            $integracion,
            'order_import',
            'webhook',
            'order',
            $orderId,
            mensaje: "Orden #{$orderId} marcada como enviada en Tiendanube",
        );
    }

    private function handleOrderPacked(TiendanubeIntegracion $integracion, ?int $orderId): void
    {
        if (! $orderId) {
            return;
        }

        $venta = \App\Models\Venta::where('tn_order_id', $orderId)->first();

        if ($venta) {
            $venta->update(['estado_envio' => 'empaquetado']);
        }

        TiendanubeLog::registrar(
            $integracion,
            'order_import',
            'webhook',
            'order',
            $orderId,
            mensaje: "Orden #{$orderId} empaquetada",
        );
    }

    private function handleCartUpdate(TiendanubeIntegracion $integracion, ?int $cartId, array $payload): void
    {
        // Los carritos abandonados se importan por batch, no individualmente
        // Este webhook sirve para tracking en tiempo real si se necesita
        if (! $integracion->import_abandoned) {
            return;
        }

        TiendanubeLog::registrar(
            $integracion,
            'cart',
            'webhook',
            'cart',
            $cartId,
            mensaje: 'Carrito actualizado (pendiente de abandono)',
        );
    }

    private function handleCustomerUpdate(TiendanubeIntegracion $integracion, ?int $customerId, array $payload): void
    {
        if (! $integracion->sync_customers || ! $customerId) {
            return;
        }

        $email = $payload['email'] ?? null;

        if (! $email) {
            return;
        }

        // Buscar cliente local por email
        $cliente = \App\Models\Cliente::where('empresa_id', $integracion->empresa_id)
            ->where('email', $email)
            ->first();

        if ($cliente) {
            // Actualizar datos
            $updateData = [];

            if (! empty($payload['name'])) {
                $updateData['nombre'] = $payload['name'];
            }
            if (! empty($payload['phone'])) {
                $updateData['telefono'] = $payload['phone'];
            }
            if (! empty($payload['identification'])) {
                $updateData['documento'] = $payload['identification'];
            }

            if (! empty($updateData)) {
                $cliente->update($updateData);
            }
        }

        TiendanubeLog::registrar(
            $integracion,
            'customer_sync',
            'webhook',
            'customer',
            $customerId,
            mensaje: "Cliente actualizado desde Tiendanube: {$email}",
        );
    }

    private function handleCategoryUpdate(TiendanubeIntegracion $integracion, ?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        TiendanubeLog::registrar(
            $integracion,
            'category_sync',
            'webhook',
            'category',
            $categoryId,
            mensaje: "Categoría actualizada en Tiendanube: #{$categoryId}",
        );
    }

    private function handleCategoryDelete(TiendanubeIntegracion $integracion, ?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        // Eliminar mapeo de categoría
        $integracion->categoryMaps()->where('tn_category_id', $categoryId)->delete();

        TiendanubeLog::registrar(
            $integracion,
            'category_sync',
            'webhook',
            'category',
            $categoryId,
            mensaje: "Mapeo de categoría eliminado: #{$categoryId}",
        );
    }

    private function handleAppUninstalled(TiendanubeIntegracion $integracion): void
    {
        TiendanubeLog::registrar(
            $integracion,
            'auth',
            'webhook',
            mensaje: 'App desinstalada de Tiendanube',
        );

        $integracion->update(['activo' => false]);
    }

    private function getEntityType(string $event): ?string
    {
        if (str_starts_with($event, 'product/')) {
            return 'product';
        }
        if (str_starts_with($event, 'order/')) {
            return 'order';
        }
        if (str_starts_with($event, 'category/')) {
            return 'category';
        }
        if (str_starts_with($event, 'customer/')) {
            return 'customer';
        }

        return null;
    }
}
