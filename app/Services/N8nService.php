<?php

namespace App\Services;

use App\Jobs\N8n\EmitirEventoN8n;
use App\Models\N8nIntegracion;
use App\Models\N8nLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nService
{
    public function emitir(int $empresaId, string $evento, array $payload = []): void
    {
        $integracion = N8nIntegracion::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if (! $integracion || ! $integracion->urlPara($evento)) {
            return;
        }

        EmitirEventoN8n::dispatch($integracion->id, $evento, $payload);
    }

    public function enviarAhora(N8nIntegracion $integracion, string $evento, array $payload): array
    {
        $url = $integracion->urlPara($evento);
        if (! $url) {
            return ['ok' => false, 'mensaje' => 'Sin URL para '.$evento];
        }

        $body = [
            'event' => $evento,
            'empresa_id' => $integracion->empresa_id,
            'occurred_at' => now()->toIso8601String(),
            'data' => $payload,
        ];

        $headers = ['Accept' => 'application/json'];
        if ($integracion->header_name && $integracion->header_value) {
            $headers[$integracion->header_name] = $integracion->header_value;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders($headers)
                ->acceptJson()
                ->post($url, $body);

            $ok = $response->successful();

            N8nLog::query()->create([
                'integracion_id' => $integracion->id,
                'evento' => $evento,
                'direccion' => 'out',
                'url' => $url,
                'http_status' => $response->status(),
                'payload' => $body,
                'status' => $ok ? 'ok' : 'error',
                'mensaje' => $ok ? null : $response->body(),
            ]);

            return ['ok' => $ok, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('n8n emit failed', ['evento' => $evento, 'error' => $e->getMessage()]);
            N8nLog::query()->create([
                'integracion_id' => $integracion->id,
                'evento' => $evento,
                'direccion' => 'out',
                'url' => $url,
                'status' => 'error',
                'mensaje' => $e->getMessage(),
                'payload' => $body,
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }
}
