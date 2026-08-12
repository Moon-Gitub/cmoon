<?php

namespace App\Services;

use App\Models\YcloudIntegracion;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YCloudService
{
    public function __construct(
        private ?YcloudIntegracion $integracion = null,
    ) {}

    public static function make(?YcloudIntegracion $integracion = null): self
    {
        return new self($integracion);
    }

    public function forIntegracion(YcloudIntegracion $integracion): self
    {
        return new self($integracion);
    }

    public function sendText(string $to, string $body, ?string $contextWamid = null): array
    {
        $payload = [
            'from' => $this->from(),
            'to' => $this->normalizePhone($to),
            'type' => 'text',
            'text' => ['body' => $body],
        ];

        if ($contextWamid) {
            $payload['context'] = ['message_id' => $contextWamid];
        }

        return $this->post('/whatsapp/messages/sendDirectly', $payload);
    }

    public function sendCatalogTemplate(string $to, string $templateName, string $language = 'es'): array
    {
        return $this->post('/whatsapp/messages/sendDirectly', [
            'from' => $this->from(),
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => [
                    [
                        'type' => 'button',
                        'sub_type' => 'catalog',
                        'index' => 0,
                    ],
                ],
            ],
        ]);
    }

    public function getBalance(): array
    {
        $response = $this->client()->get($this->base().'/balance');

        return $this->decode($response);
    }

    private function post(string $path, array $payload): array
    {
        $response = $this->client()->post($this->base().$path, $payload);

        if ($response->failed()) {
            Log::warning('YCloud API error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        return $this->decode($response);
    }

    private function decode(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : ['raw' => $response->body(), 'ok' => $response->successful()];
    }

    private function client()
    {
        return Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $this->apiKey(),
            ]);
    }

    private function base(): string
    {
        return rtrim((string) config('ycloud.api_base'), '/');
    }

    private function apiKey(): string
    {
        if ($this->integracion) {
            return $this->integracion->resolvedApiKey();
        }

        return (string) config('ycloud.api_key');
    }

    private function from(): string
    {
        if ($this->integracion) {
            return $this->integracion->phoneE164();
        }

        return (string) config('ycloud.phone_from');
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? $phone;

        if ($phone !== '' && ! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
