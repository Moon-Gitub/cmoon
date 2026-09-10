<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaLlmService
{
    public function __construct(
        private IaCupoService $cupo,
        private IaConfigService $config,
    ) {}

    /** @return array{ok: bool, texto: string, cupo: array, limite?: bool} */
    public function completar(int $empresaId, string $system, string $user, int $maxTokens = 400): array
    {
        $cfg = $this->config->resolver();
        if ($cfg['api_key'] === '') {
            return [
                'ok' => false,
                'texto' => 'IA no configurada. El supermegaadmin debe cargar OpenAI/OpenRouter en Admin → IA.',
                'cupo' => $this->cupo->resumen($empresaId),
            ];
        }

        if (! $this->cupo->consumir($empresaId)) {
            return [
                'ok' => false,
                'texto' => $this->cupo->mensajeLimite($empresaId),
                'cupo' => $this->cupo->resumen($empresaId),
                'limite' => true,
            ];
        }

        try {
            $headers = [];
            if ($cfg['provider'] === 'openrouter') {
                $headers['HTTP-Referer'] = config('app.url');
                $headers['X-Title'] = config('app.name', 'POSMoon');
            }

            $response = Http::timeout(25)
                ->withToken($cfg['api_key'])
                ->withHeaders($headers)
                ->acceptJson()
                ->post($cfg['base_url'].'/chat/completions', [
                    'model' => $cfg['model'],
                    'temperature' => 0.2,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            $texto = trim((string) data_get($response->json(), 'choices.0.message.content'));
            if ($texto === '') {
                return [
                    'ok' => false,
                    'texto' => 'La IA no devolvió texto. Reintentá.',
                    'cupo' => $this->cupo->resumen($empresaId),
                ];
            }

            return [
                'ok' => true,
                'texto' => $texto,
                'cupo' => $this->cupo->resumen($empresaId),
            ];
        } catch (\Throwable $e) {
            Log::warning('IaLlmService: '.$e->getMessage());

            return [
                'ok' => false,
                'texto' => 'El servicio de IA no respondió.',
                'cupo' => $this->cupo->resumen($empresaId),
            ];
        }
    }
}
