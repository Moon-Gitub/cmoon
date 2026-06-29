<?php

namespace App\Services;

use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TiendanubeService
{
    private ?TiendanubeIntegracion $integracion = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiUrl,
        private readonly string $apiVersion,
        private readonly string $authUrl,
        private readonly string $userAgent,
    ) {}

    public static function make(): self
    {
        return new self(
            clientId: config('tiendanube.client_id'),
            clientSecret: config('tiendanube.client_secret'),
            apiUrl: config('tiendanube.api_url'),
            apiVersion: config('tiendanube.api_version'),
            authUrl: config('tiendanube.auth_url'),
            userAgent: config('tiendanube.user_agent'),
        );
    }

    public function forIntegracion(TiendanubeIntegracion $integracion): self
    {
        $this->integracion = $integracion;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OAuth
    // ─────────────────────────────────────────────────────────────────────────

    public function getAuthorizationUrl(string $state): string
    {
        return "{$this->authUrl}/apps/{$this->clientId}/authorize?state={$state}";
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::acceptJson()
            ->post("{$this->authUrl}/apps/authorize/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Error al obtener token de Tiendanube: '.$response->body()
            );
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP Client
    // ─────────────────────────────────────────────────────────────────────────

    private function client(): PendingRequest
    {
        if (! $this->integracion) {
            throw new \RuntimeException('Debe llamar forIntegracion() antes de hacer requests');
        }

        return Http::baseUrl($this->integracion->apiBaseUrl())
            ->withToken($this->integracion->access_token)
            ->withHeaders([
                'User-Agent' => $this->userAgent,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->retry(
                config('tiendanube.sync.retry_attempts', 3),
                config('tiendanube.sync.retry_delay_ms', 1000),
                fn ($exception) => $exception instanceof RequestException && $exception->response->status() >= 500
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
            TiendanubeLog::registrar(
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

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function getStore(): ?array
    {
        try {
            $response = $this->client()->get('/');

            $this->log('auth', 'pull', null, $response->json());

            return $response->json();
        } catch (\Throwable $e) {
            $this->log('auth', 'pull', null, null, 'error', $e->getMessage());

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Products
    // ─────────────────────────────────────────────────────────────────────────

    public function getProducts(array $params = []): array
    {
        $defaults = ['per_page' => 50, 'page' => 1];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/products', $params);

        $this->log('product_sync', 'pull', $params, ['count' => count($response->json())]);

        return $response->json();
    }

    public function getProduct(int $productId): ?array
    {
        try {
            $response = $this->client()->get("/products/{$productId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createProduct(array $data): array
    {
        $response = $this->client()->post('/products', $data);

        $result = $response->json();

        $this->log(
            'product_sync',
            'push',
            $data,
            $result,
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Producto creado en Tiendanube' : $response->body(),
            'product',
            $result['id'] ?? null,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear producto: '.$response->body());
        }

        return $result;
    }

    public function updateProduct(int $productId, array $data): array
    {
        $response = $this->client()->put("/products/{$productId}", $data);

        $result = $response->json();

        $this->log(
            'product_sync',
            'push',
            $data,
            $result,
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Producto actualizado' : $response->body(),
            'product',
            $productId,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar producto: '.$response->body());
        }

        return $result;
    }

    public function deleteProduct(int $productId): bool
    {
        $response = $this->client()->delete("/products/{$productId}");

        $this->log(
            'product_sync',
            'push',
            ['product_id' => $productId],
            null,
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Producto eliminado' : $response->body(),
            'product',
            $productId,
        );

        return $response->successful();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product Images
    // ─────────────────────────────────────────────────────────────────────────

    public function getProductImages(int $productId): array
    {
        $response = $this->client()->get("/products/{$productId}/images");

        return $response->json() ?? [];
    }

    public function uploadProductImage(int $productId, string $imageUrl): ?array
    {
        $response = $this->client()->post("/products/{$productId}/images", [
            'src' => $imageUrl,
        ]);

        if ($response->failed()) {
            $this->log(
                'product_sync',
                'push',
                ['product_id' => $productId, 'image_url' => $imageUrl],
                null,
                'error',
                'Error subiendo imagen: '.$response->body(),
                'product',
                $productId,
            );

            return null;
        }

        return $response->json();
    }

    public function uploadProductImageBase64(int $productId, string $base64, string $filename): ?array
    {
        $response = $this->client()->post("/products/{$productId}/images", [
            'filename' => $filename,
            'attachment' => $base64,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    public function deleteProductImage(int $productId, int $imageId): bool
    {
        $response = $this->client()->delete("/products/{$productId}/images/{$imageId}");

        return $response->successful();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stock
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStock(int $productId, float $quantity, ?int $variantId = null): array
    {
        $data = [
            'action' => 'replace',
            'value' => (int) $quantity,
        ];

        if ($variantId) {
            $data['id'] = $variantId;
        }

        $response = $this->client()->post("/products/{$productId}/variants/stock", $data);

        $result = $response->json();

        $this->log(
            'stock_sync',
            'push',
            array_merge($data, ['product_id' => $productId]),
            $result,
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? "Stock actualizado a {$quantity}" : $response->body(),
            'stock',
            $productId,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar stock: '.$response->body());
        }

        return $result;
    }

    public function adjustStock(int $productId, float $delta, ?int $variantId = null): array
    {
        $data = [
            'action' => 'variation',
            'value' => (int) $delta,
        ];

        if ($variantId) {
            $data['id'] = $variantId;
        }

        $response = $this->client()->post("/products/{$productId}/variants/stock", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al ajustar stock: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Categories
    // ─────────────────────────────────────────────────────────────────────────

    public function getCategories(array $params = []): array
    {
        $response = $this->client()->get('/categories', $params);

        return $response->json();
    }

    public function createCategory(array $data): array
    {
        $response = $this->client()->post('/categories', $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear categoría: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Orders
    // ─────────────────────────────────────────────────────────────────────────

    public function getOrders(array $params = []): array
    {
        $defaults = ['per_page' => 50, 'page' => 1];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/orders', $params);

        $this->log('order_import', 'pull', $params, ['count' => count($response->json())]);

        return $response->json();
    }

    public function getOrder(int $orderId): ?array
    {
        try {
            $response = $this->client()->get("/orders/{$orderId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function updateOrderStatus(int $orderId, string $status): array
    {
        $response = $this->client()->post("/orders/{$orderId}/fulfill");

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar orden: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhooks
    // ─────────────────────────────────────────────────────────────────────────

    public function getWebhooks(): array
    {
        $response = $this->client()->get('/webhooks');

        return $response->json();
    }

    public function createWebhook(string $event, string $url): array
    {
        $response = $this->client()->post('/webhooks', [
            'event' => $event,
            'url' => $url,
        ]);

        $this->log(
            'webhook',
            'push',
            ['event' => $event, 'url' => $url],
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? "Webhook {$event} registrado" : $response->body(),
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear webhook: '.$response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(int $webhookId): bool
    {
        $response = $this->client()->delete("/webhooks/{$webhookId}");

        return $response->successful();
    }

    public function registerAllWebhooks(string $baseUrl): array
    {
        $events = config('tiendanube.webhook.events', []);
        $registered = [];

        foreach ($events as $event) {
            try {
                $webhook = $this->createWebhook($event, $baseUrl);
                $registered[] = $webhook;
            } catch (\Throwable $e) {
                $this->log('webhook', 'push', ['event' => $event], null, 'error', $e->getMessage());
            }
        }

        return $registered;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Coupons
    // ─────────────────────────────────────────────────────────────────────────

    public function getCoupons(array $params = []): array
    {
        $defaults = ['per_page' => 50, 'page' => 1];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/coupons', $params);

        return $response->json() ?? [];
    }

    public function getCoupon(int $couponId): ?array
    {
        try {
            $response = $this->client()->get("/coupons/{$couponId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createCoupon(array $data): array
    {
        $response = $this->client()->post('/coupons', $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear cupón: '.$response->body());
        }

        $this->log(
            'coupon_sync',
            'push',
            $data,
            $response->json(),
            'ok',
            'Cupón creado: '.($data['code'] ?? 'N/A'),
        );

        return $response->json();
    }

    public function updateCoupon(int $couponId, array $data): array
    {
        $response = $this->client()->put("/coupons/{$couponId}", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar cupón: '.$response->body());
        }

        return $response->json();
    }

    public function deleteCoupon(int $couponId): bool
    {
        $response = $this->client()->delete("/coupons/{$couponId}");

        return $response->successful();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Customers
    // ─────────────────────────────────────────────────────────────────────────

    public function getCustomers(array $params = []): array
    {
        $defaults = ['per_page' => 50, 'page' => 1];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/customers', $params);

        return $response->json();
    }

    public function getCustomer(int $customerId): ?array
    {
        try {
            $response = $this->client()->get("/customers/{$customerId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createCustomer(array $data): array
    {
        $response = $this->client()->post('/customers', $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear cliente: '.$response->body());
        }

        return $response->json();
    }

    public function updateCustomer(int $customerId, array $data): array
    {
        $response = $this->client()->put("/customers/{$customerId}", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar cliente: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shipping Carriers
    // ─────────────────────────────────────────────────────────────────────────

    public function getShippingCarriers(): array
    {
        $response = $this->client()->get('/shipping_carriers');

        return $response->json() ?? [];
    }

    public function getShippingCarrier(int $carrierId): ?array
    {
        try {
            $response = $this->client()->get("/shipping_carriers/{$carrierId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createShippingCarrier(array $data): array
    {
        $response = $this->client()->post('/shipping_carriers', $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear carrier: '.$response->body());
        }

        return $response->json();
    }

    public function updateShippingCarrier(int $carrierId, array $data): array
    {
        $response = $this->client()->put("/shipping_carriers/{$carrierId}", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar carrier: '.$response->body());
        }

        return $response->json();
    }

    public function deleteShippingCarrier(int $carrierId): bool
    {
        $response = $this->client()->delete("/shipping_carriers/{$carrierId}");

        return $response->successful();
    }

    public function getShippingCarrierOptions(int $carrierId): array
    {
        $response = $this->client()->get("/shipping_carriers/{$carrierId}/options");

        return $response->json() ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fulfillment (Despacho de pedidos)
    // ─────────────────────────────────────────────────────────────────────────

    public function fulfillOrder(int $orderId, array $data = []): array
    {
        $response = $this->client()->post("/orders/{$orderId}/fulfill", $data);

        $this->log(
            'fulfillment',
            'push',
            array_merge(['order_id' => $orderId], $data),
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Pedido marcado como despachado' : $response->body(),
            'order',
            $orderId,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al despachar pedido: '.$response->body());
        }

        return $response->json();
    }

    public function packOrder(int $orderId): array
    {
        $response = $this->client()->post("/orders/{$orderId}/pack");

        if ($response->failed()) {
            throw new \RuntimeException('Error al empaquetar pedido: '.$response->body());
        }

        return $response->json();
    }

    public function addTrackingToOrder(int $orderId, string $trackingNumber, ?string $trackingUrl = null, ?int $carrierId = null): array
    {
        $data = [
            'shipping_tracking_number' => $trackingNumber,
        ];

        if ($trackingUrl) {
            $data['shipping_tracking_url'] = $trackingUrl;
        }

        if ($carrierId) {
            $data['shipping_carrier_id'] = $carrierId;
        }

        $response = $this->client()->put("/orders/{$orderId}", $data);

        $this->log(
            'tracking',
            'push',
            $data,
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? "Tracking agregado: {$trackingNumber}" : $response->body(),
            'order',
            $orderId,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al agregar tracking: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scripts (Pixels de Tracking)
    // ─────────────────────────────────────────────────────────────────────────

    public function getScripts(): array
    {
        $response = $this->client()->get('/scripts');

        return $response->json() ?? [];
    }

    public function createScript(string $src, string $event = 'onload', ?string $where = null): array
    {
        $data = [
            'src' => $src,
            'event' => $event, // 'onload' o 'beforeunload'
        ];

        if ($where) {
            $data['where'] = $where; // 'store', 'checkout', 'all'
        }

        $response = $this->client()->post('/scripts', $data);

        $this->log(
            'script',
            'push',
            $data,
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Script agregado' : $response->body(),
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear script: '.$response->body());
        }

        return $response->json();
    }

    public function deleteScript(int $scriptId): bool
    {
        $response = $this->client()->delete("/scripts/{$scriptId}");

        return $response->successful();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Metafields (Campos personalizados)
    // ─────────────────────────────────────────────────────────────────────────

    public function getProductMetafields(int $productId): array
    {
        $response = $this->client()->get("/products/{$productId}/metafields");

        return $response->json() ?? [];
    }

    public function createProductMetafield(int $productId, string $namespace, string $key, $value, string $valueType = 'string'): array
    {
        $data = [
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
            'value_type' => $valueType, // 'string', 'integer', 'json'
        ];

        $response = $this->client()->post("/products/{$productId}/metafields", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear metafield: '.$response->body());
        }

        return $response->json();
    }

    public function updateProductMetafield(int $productId, int $metafieldId, $value): array
    {
        $response = $this->client()->put("/products/{$productId}/metafields/{$metafieldId}", [
            'value' => $value,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar metafield: '.$response->body());
        }

        return $response->json();
    }

    public function deleteProductMetafield(int $productId, int $metafieldId): bool
    {
        $response = $this->client()->delete("/products/{$productId}/metafields/{$metafieldId}");

        return $response->successful();
    }

    public function getOrderMetafields(int $orderId): array
    {
        $response = $this->client()->get("/orders/{$orderId}/metafields");

        return $response->json() ?? [];
    }

    public function createOrderMetafield(int $orderId, string $namespace, string $key, $value, string $valueType = 'string'): array
    {
        $response = $this->client()->post("/orders/{$orderId}/metafields", [
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
            'value_type' => $valueType,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear metafield de orden: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Locations (Multi-ubicación de stock)
    // ─────────────────────────────────────────────────────────────────────────

    public function getLocations(): array
    {
        $response = $this->client()->get('/locations');

        return $response->json() ?? [];
    }

    public function getLocation(int $locationId): ?array
    {
        try {
            $response = $this->client()->get("/locations/{$locationId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createLocation(array $data): array
    {
        $response = $this->client()->post('/locations', $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear ubicación: '.$response->body());
        }

        return $response->json();
    }

    public function updateLocation(int $locationId, array $data): array
    {
        $response = $this->client()->put("/locations/{$locationId}", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar ubicación: '.$response->body());
        }

        return $response->json();
    }

    public function updateStockAtLocation(int $productId, int $locationId, int $quantity, ?int $variantId = null): array
    {
        $data = [
            'location_id' => $locationId,
            'stock' => $quantity,
        ];

        if ($variantId) {
            $data['variant_id'] = $variantId;
        }

        $response = $this->client()->put("/products/{$productId}/variants/stock", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar stock en ubicación: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Draft Orders (Órdenes borrador)
    // ─────────────────────────────────────────────────────────────────────────

    public function getDraftOrders(array $params = []): array
    {
        $defaults = ['per_page' => 50, 'page' => 1];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/draft_orders', $params);

        return $response->json() ?? [];
    }

    public function getDraftOrder(int $draftOrderId): ?array
    {
        try {
            $response = $this->client()->get("/draft_orders/{$draftOrderId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createDraftOrder(array $data): array
    {
        $response = $this->client()->post('/draft_orders', $data);

        $this->log(
            'draft_order',
            'push',
            $data,
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Orden borrador creada' : $response->body(),
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear orden borrador: '.$response->body());
        }

        return $response->json();
    }

    public function updateDraftOrder(int $draftOrderId, array $data): array
    {
        $response = $this->client()->put("/draft_orders/{$draftOrderId}", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar orden borrador: '.$response->body());
        }

        return $response->json();
    }

    public function deleteDraftOrder(int $draftOrderId): bool
    {
        $response = $this->client()->delete("/draft_orders/{$draftOrderId}");

        return $response->successful();
    }

    public function convertDraftToOrder(int $draftOrderId): array
    {
        $response = $this->client()->post("/draft_orders/{$draftOrderId}/convert");

        $this->log(
            'draft_order',
            'push',
            ['draft_order_id' => $draftOrderId],
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? 'Borrador convertido a orden' : $response->body(),
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al convertir borrador: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Abandoned Carts (Carritos abandonados)
    // ─────────────────────────────────────────────────────────────────────────

    public function getAbandonedCheckouts(array $params = []): array
    {
        $defaults = ['per_page' => 50, 'page' => 1];
        $params = array_merge($defaults, $params);

        $response = $this->client()->get('/abandoned_checkouts', $params);

        return $response->json() ?? [];
    }

    public function getAbandonedCheckout(int $checkoutId): ?array
    {
        try {
            $response = $this->client()->get("/abandoned_checkouts/{$checkoutId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Price Lists (Listas de precios mayoristas)
    // ─────────────────────────────────────────────────────────────────────────

    public function getPriceLists(): array
    {
        $response = $this->client()->get('/price_lists');

        return $response->json() ?? [];
    }

    public function getPriceList(int $priceListId): ?array
    {
        try {
            $response = $this->client()->get("/price_lists/{$priceListId}");

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    public function createPriceList(string $name, ?string $description = null): array
    {
        $data = ['name' => $name];

        if ($description) {
            $data['description'] = $description;
        }

        $response = $this->client()->post('/price_lists', $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear lista de precios: '.$response->body());
        }

        return $response->json();
    }

    public function updatePriceList(int $priceListId, array $data): array
    {
        $response = $this->client()->put("/price_lists/{$priceListId}", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al actualizar lista de precios: '.$response->body());
        }

        return $response->json();
    }

    public function deletePriceList(int $priceListId): bool
    {
        $response = $this->client()->delete("/price_lists/{$priceListId}");

        return $response->successful();
    }

    public function getPriceListPrices(int $priceListId, array $params = []): array
    {
        $response = $this->client()->get("/price_lists/{$priceListId}/prices", $params);

        return $response->json() ?? [];
    }

    public function setPriceListPrice(int $priceListId, int $variantId, float $price): array
    {
        $response = $this->client()->put("/price_lists/{$priceListId}/prices/{$variantId}", [
            'price' => $price,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error al establecer precio: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Promotional Prices (Precios promocionales)
    // ─────────────────────────────────────────────────────────────────────────

    public function setPromotionalPrice(int $productId, int $variantId, float $promotionalPrice, ?string $startDate = null, ?string $endDate = null): array
    {
        $data = [
            'promotional_price' => $promotionalPrice,
        ];

        if ($startDate) {
            $data['promotional_price_start_date'] = $startDate;
        }

        if ($endDate) {
            $data['promotional_price_end_date'] = $endDate;
        }

        $response = $this->client()->put("/products/{$productId}/variants/{$variantId}", $data);

        $this->log(
            'promo_price',
            'push',
            array_merge($data, ['product_id' => $productId, 'variant_id' => $variantId]),
            $response->json(),
            $response->successful() ? 'ok' : 'error',
            $response->successful() ? "Precio promocional: \${$promotionalPrice}" : $response->body(),
            'product',
            $productId,
        );

        if ($response->failed()) {
            throw new \RuntimeException('Error al establecer precio promocional: '.$response->body());
        }

        return $response->json();
    }

    public function removePromotionalPrice(int $productId, int $variantId): array
    {
        $response = $this->client()->put("/products/{$productId}/variants/{$variantId}", [
            'promotional_price' => null,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error al quitar precio promocional: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transactions (Transacciones de pago)
    // ─────────────────────────────────────────────────────────────────────────

    public function getOrderTransactions(int $orderId): array
    {
        $response = $this->client()->get("/orders/{$orderId}/transactions");

        return $response->json() ?? [];
    }

    public function createTransaction(int $orderId, array $data): array
    {
        $response = $this->client()->post("/orders/{$orderId}/transactions", $data);

        if ($response->failed()) {
            throw new \RuntimeException('Error al crear transacción: '.$response->body());
        }

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store Info
    // ─────────────────────────────────────────────────────────────────────────

    public function getStorePaymentProviders(): array
    {
        $response = $this->client()->get('/payment_providers');

        return $response->json() ?? [];
    }

    public function getStoreShippingCarriersList(): array
    {
        $response = $this->client()->get('/shipping_carriers');

        return $response->json() ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (! $this->integracion || ! $this->integracion->webhook_secret) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->clientSecret);

        return hash_equals($expected, $signature);
    }

    public function testConnection(): bool
    {
        try {
            $store = $this->getStore();

            return $store !== null && isset($store['id']);
        } catch (\Throwable) {
            return false;
        }
    }
}
