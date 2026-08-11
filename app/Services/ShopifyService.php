<?php

namespace App\Services;

use App\Models\ShopifyIntegracion;
use App\Models\ShopifyLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para Shopify Admin REST API.
 *
 * Auth: header X-Shopify-Access-Token (custom app / Admin API token).
 * Docs: https://shopify.dev/docs/api/admin-rest
 */
class ShopifyService
{
    private ?ShopifyIntegracion $integracion = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $apiSecret,
        private readonly string $apiVersion,
    ) {}

    public static function make(): self
    {
        return new self(
            apiKey: config('shopify.api_key'),
            apiSecret: config('shopify.api_secret'),
            apiVersion: config('shopify.api_version', '2025-01'),
        );
    }

    public function forIntegracion(ShopifyIntegracion $integracion): self
    {
        $this->integracion = $integracion;

        return $this;
    }

    /**
     * Verifica firma de webhook Shopify (X-Shopify-Hmac-Sha256).
     *
     * @see https://shopify.dev/docs/apps/build/webhooks/subscribe/https
     */
    public static function verifyWebhookHmac(string $rawBody, ?string $hmacHeader, string $secret): bool
    {
        if ($hmacHeader === null || $hmacHeader === '' || $secret === '') {
            return false;
        }

        $calculated = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($calculated, $hmacHeader);
    }

    private function client(): PendingRequest
    {
        if (! $this->integracion) {
            throw new \RuntimeException('Debe llamar forIntegracion() antes de hacer requests');
        }

        return Http::baseUrl($this->integracion->apiBaseUrl())
            ->withHeaders([
                'X-Shopify-Access-Token' => $this->integracion->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->retry(
                config('shopify.sync.retry_attempts', 3),
                config('shopify.sync.retry_delay_ms', 1000),
                fn ($exception) => $exception instanceof RequestException
                    && $exception->response
                    && $exception->response->status() >= 500
            );
    }

    private function log(
        string $tipo,
        string $direccion,
        ?array $request,
        ?array $response,
        string $status = 'ok',
        ?string $mensaje = null,
        ?string $entidadTipo = null,
        ?int $entidadId = null,
    ): void {
        if ($this->integracion) {
            ShopifyLog::registrar(
                $this->integracion,
                $tipo,
                $direccion,
                $entidadTipo,
                $entidadId,
                $request,
                $response,
                $status,
                $mensaje,
            );
        }
    }

    public function getShop(): ?array
    {
        try {
            $response = $this->client()->get('/shop.json');
            $data = $response->json('shop');
            $this->log('auth', 'pull', null, $data);

            return $data;
        } catch (\Throwable $e) {
            $this->log('auth', 'pull', null, null, 'error', $e->getMessage());

            return null;
        }
    }

    public function getProducts(array $params = []): array
    {
        $defaults = [
            'limit' => config('shopify.sync.chunk_size', 50),
        ];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/products.json', $params);
        $products = $response->json('products') ?? [];

        $this->log('product_sync', 'pull', $params, ['count' => count($products)]);

        return [
            'products' => $products,
            'link' => $response->header('Link'),
        ];
    }

    public function getProduct(int $productId): ?array
    {
        try {
            $response = $this->client()->get("/products/{$productId}.json");

            return $response->json('product');
        } catch (\Throwable) {
            return null;
        }
    }

    public function createProduct(array $product): array
    {
        $response = $this->client()->post('/products.json', ['product' => $product]);
        $result = $response->json('product') ?? $response->json();

        $this->log(
            'product_sync',
            'push',
            $product,
            $result,
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Producto creado en Shopify' : $response->body(),
            'product',
            $result['id'] ?? null,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear producto en Shopify: '.$response->body());
        }

        return $result;
    }

    public function updateProduct(int $productId, array $product): array
    {
        $response = $this->client()->put("/products/{$productId}.json", ['product' => $product]);
        $result = $response->json('product') ?? $response->json();

        $this->log(
            'product_sync',
            'push',
            $product,
            $result,
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Producto actualizado en Shopify' : $response->body(),
            'product',
            $productId,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar producto en Shopify: '.$response->body());
        }

        return $result;
    }

    public function getOrder(int $orderId): ?array
    {
        try {
            $response = $this->client()->get("/orders/{$orderId}.json");

            return $response->json('order');
        } catch (\Throwable $e) {
            $this->log('order_import', 'pull', ['id' => $orderId], null, 'error', $e->getMessage(), 'order', $orderId);

            return null;
        }
    }

    public function registerWebhook(string $topic, string $address): ?array
    {
        $payload = [
            'webhook' => [
                'topic' => $topic,
                'address' => $address,
                'format' => 'json',
            ],
        ];

        try {
            $response = $this->client()->post('/webhooks.json', $payload);
            $result = $response->json('webhook');

            $this->log(
                'webhook',
                'push',
                $payload,
                $result,
                $response->successful() ? 'ok' : 'error',
                $response->successful() ? "Webhook {$topic} registrado" : $response->body(),
            );

            return $result;
        } catch (\Throwable $e) {
            $this->log('webhook', 'push', $payload, null, 'error', $e->getMessage());

            return null;
        }
    }

    public function registerDefaultWebhooks(string $address): void
    {
        foreach (config('shopify.webhook.topics', []) as $topic) {
            $this->registerWebhook($topic, $address);
        }
    }

    public function listWebhooks(): array
    {
        try {
            $response = $this->client()->get('/webhooks.json');

            return $response->json('webhooks') ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function deleteWebhook(int $webhookId): void
    {
        $this->client()->delete("/webhooks/{$webhookId}.json");
    }
}
