<?php

namespace App\Services;

use App\Models\IaConfiguracion;

class IaConfigService
{
    /** @return array<string, array{label: string, base_url: string, model: string}> */
    public function providers(): array
    {
        return [
            'openai' => [
                'label' => 'OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
            ],
            'openrouter' => [
                'label' => 'OpenRouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o-mini',
            ],
            'groq' => [
                'label' => 'Groq',
                'base_url' => 'https://api.groq.com/openai/v1',
                'model' => 'llama-3.3-70b-versatile',
            ],
            'custom' => [
                'label' => 'Custom (compatible OpenAI)',
                'base_url' => '',
                'model' => '',
            ],
        ];
    }

    public function config(): IaConfiguracion
    {
        return IaConfiguracion::actual();
    }

    /** @return array{api_key: string, base_url: string, model: string, provider: string, fuente: string} */
    public function resolver(): array
    {
        $cfg = $this->config();
        $provider = $cfg->provider ?: 'openai';
        $preset = $this->providers()[$provider] ?? $this->providers()['openai'];

        $apiKey = trim((string) ($cfg->api_key ?? ''));
        $baseUrl = trim((string) ($cfg->base_url ?: $preset['base_url']));
        $model = trim((string) ($cfg->model ?: $preset['model']));
        $fuente = 'db';

        if ($apiKey === '' || ! $cfg->activo) {
            $apiKey = trim((string) config('ycloud.openai_api_key'));
            $baseUrl = rtrim((string) config('ycloud.openai_base_url'), '/') ?: $baseUrl;
            $model = (string) (config('ycloud.openai_model') ?: $model);
            $fuente = 'env';
        }

        return [
            'api_key' => $apiKey,
            'base_url' => rtrim($baseUrl, '/'),
            'model' => $model,
            'provider' => $provider,
            'fuente' => $fuente,
            'activo' => (bool) $cfg->activo || $apiKey !== '',
        ];
    }

    public function guardar(array $datos): IaConfiguracion
    {
        $cfg = $this->config();
        $provider = $datos['provider'] ?? $cfg->provider;
        $preset = $this->providers()[$provider] ?? $this->providers()['custom'];

        $payload = [
            'provider' => $provider,
            'base_url' => $datos['base_url'] ?? $preset['base_url'] ?: $cfg->base_url,
            'model' => $datos['model'] ?? $preset['model'] ?: $cfg->model,
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        if (array_key_exists('api_key', $datos)) {
            $key = trim((string) $datos['api_key']);
            // Vacío = no tocar la clave guardada
            if ($key !== '' && $key !== '********') {
                $payload['api_key'] = $key;
            }
        }

        $cfg->update($payload);

        return $cfg->fresh();
    }
}
