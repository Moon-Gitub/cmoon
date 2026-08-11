<?php

namespace App\Http\Controllers;

use App\Jobs\Shopify\ImportShopifyOrder;
use App\Jobs\Shopify\SyncProductFromShopify;
use App\Models\ShopifyIntegracion;
use App\Models\ShopifyLog;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $hmac = $request->header('X-Shopify-Hmac-Sha256');

        if (! $topic || ! $shopDomain) {
            return response('Bad request', 400);
        }

        $integracion = ShopifyIntegracion::query()
            ->where('activo', true)
            ->get()
            ->first(fn (ShopifyIntegracion $i) => $i->normalizedDomain() === strtolower($shopDomain));

        if (! $integracion) {
            // Fallback: intentar match parcial (sin .myshopify.com)
            $integracion = ShopifyIntegracion::where('activo', true)
                ->where('store_domain', 'like', '%'.explode('.', $shopDomain)[0].'%')
                ->first();
        }

        if (! $integracion) {
            return response('Store not found', 404);
        }

        $secret = $integracion->hmacSecret();
        if (! ShopifyService::verifyWebhookHmac($rawBody, $hmac, $secret)) {
            ShopifyLog::registrar(
                $integracion,
                'webhook',
                'webhook',
                mensaje: "HMAC inválido para topic {$topic}",
                status: 'error',
            );

            return response('Invalid signature', 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $entityId = isset($payload['id']) ? (int) $payload['id'] : null;

        ShopifyLog::registrar(
            $integracion,
            'webhook',
            'webhook',
            entidadTipo: $this->entityType($topic),
            entidadId: $entityId,
            request: ['topic' => $topic, 'id' => $entityId],
            mensaje: "Webhook recibido: {$topic}",
        );

        $this->dispatchTopic($integracion, $topic, $entityId, $payload);

        return response('OK', 200);
    }

    private function dispatchTopic(
        ShopifyIntegracion $integracion,
        string $topic,
        ?int $entityId,
        array $payload,
    ): void {
        match ($topic) {
            'orders/create', 'orders/paid' => $this->handleOrder($integracion, $entityId, $payload),
            'products/create', 'products/update' => $this->handleProduct($integracion, $entityId),
            'app/uninstalled' => $integracion->update(['activo' => false]),
            default => null,
        };
    }

    private function handleOrder(ShopifyIntegracion $integracion, ?int $orderId, array $payload): void
    {
        if (! $orderId || ! $integracion->sync_orders) {
            return;
        }

        ImportShopifyOrder::dispatch($integracion, $orderId, $payload);
    }

    private function handleProduct(ShopifyIntegracion $integracion, ?int $productId): void
    {
        if (! $productId || ! $integracion->auto_create_products) {
            return;
        }

        SyncProductFromShopify::dispatch($integracion, $productId);
    }

    private function entityType(string $topic): string
    {
        return match (true) {
            str_starts_with($topic, 'orders/') => 'order',
            str_starts_with($topic, 'products/') => 'product',
            default => 'other',
        };
    }
}
