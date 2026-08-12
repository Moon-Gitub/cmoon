<?php

namespace App\Jobs\N8n;

use App\Models\N8nIntegracion;
use App\Services\N8nService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmitirEventoN8n implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $integracionId,
        public string $evento,
        public array $payload,
    ) {}

    public function handle(N8nService $n8n): void
    {
        $integracion = N8nIntegracion::query()->find($this->integracionId);
        if (! $integracion?->activo) {
            return;
        }

        $n8n->enviarAhora($integracion, $this->evento, $this->payload);
    }
}
