<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaLlmService
{
    public function __construct(
        private IaCupoService $cupo,
    ) {}

    /** @return array{ok: bool, texto: string, cupo: array, limite?: bool} */
    public function completar(int $empresaId, string $system, string $user, int $maxTokens = 400): array
    {
        $apiKey = (string) config('ycloud.openai_api_key');
        if ($apiKey === '') {
            return [
                'ok' => false,
                'texto' => 'IA no configurada (OPENAI_API_KEY). Pedile a soporte que la active; el cupo no se descontó.',
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

        // Si falló el LLM, devolver el cupo es complejo (ya se consumió). Aceptable: 1 intento.
        $base = rtrim((string) config('ycloud.openai_base_url'), '/');

        try {
            $response = Http::timeout(25)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($base.'/chat/completions', [
                    'model' => config('ycloud.openai_model'),
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
