<?php

namespace App\Jobs\WhatsApp;

use App\Models\YcloudConversacion;
use App\Models\YcloudIntegracion;
use App\Models\YcloudMensaje;
use App\Services\ProductoConsultaIaService;
use App\Services\YCloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResponderConsultaWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $integracionId,
        public array $inbound,
    ) {}

    public function handle(ProductoConsultaIaService $consultas, YCloudService $ycloud): void
    {
        $integracion = YcloudIntegracion::query()->find($this->integracionId);
        if (! $integracion?->activo || ! $integracion->bot_activo) {
            return;
        }

        $from = (string) ($this->inbound['from'] ?? '');
        $to = (string) ($this->inbound['to'] ?? '');
        $wamid = (string) ($this->inbound['wamid'] ?? $this->inbound['id'] ?? '');
        $nombre = data_get($this->inbound, 'customerProfile.name');
        $texto = $this->extraerTexto($this->inbound);

        if ($from === '' || $texto === '') {
            return;
        }

        $conversacion = YcloudConversacion::query()->firstOrCreate(
            [
                'integracion_id' => $integracion->id,
                'telefono' => $from,
            ],
            ['nombre' => $nombre]
        );

        $conversacion->update([
            'nombre' => $nombre ?: $conversacion->nombre,
            'last_inbound_at' => now(),
        ]);

        $mensaje = YcloudMensaje::query()->create([
            'integracion_id' => $integracion->id,
            'conversacion_id' => $conversacion->id,
            'direccion' => 'in',
            'from_phone' => $from,
            'to_phone' => $to,
            'wamid' => $wamid ?: null,
            'body' => $texto,
            'raw' => $this->inbound,
            'status' => 'ok',
        ]);

        $integracion->update(['last_message_at' => now()]);

        if (! $integracion->auto_reply) {
            return;
        }

        if ($conversacion->enHandoff()) {
            $mensaje->update(['handoff' => true, 'status' => 'handoff']);

            return;
        }

        $cupo = app(\App\Services\IaCupoService::class);
        $service = $ycloud->forIntegracion($integracion);

        if (! $cupo->consumir((int) $integracion->empresa_id)) {
            $aviso = $cupo->mensajeLimite((int) $integracion->empresa_id);
            $service->sendText($from, $aviso, $wamid ?: null);
            $conversacion->update(['handoff_until' => now()->addHours(2)]);
            $mensaje->update(['handoff' => true, 'status' => 'limite', 'respuesta' => $aviso]);

            return;
        }

        $resultado = $consultas->responder((int) $integracion->empresa_id, $texto);

        app(\App\Services\N8nService::class)->emitir((int) $integracion->empresa_id, 'whatsapp.inbound', [
            'from' => $from,
            'texto' => $texto,
            'producto_ids' => $resultado['producto_ids'],
        ]);

        if ($resultado['handoff']) {
            $conversacion->update(['handoff_until' => now()->addHours(2)]);
        }

        $enviado = null;

        if ($resultado['quiere_catalogo'] && filled($integracion->catalog_template)) {
            $enviado = $service->sendCatalogTemplate($from, $integracion->catalog_template);
        }

        $enviado = $service->sendText($from, $resultado['texto'], $wamid ?: null);

        $ok = ! isset($enviado['error']);

        YcloudMensaje::query()->create([
            'integracion_id' => $integracion->id,
            'conversacion_id' => $conversacion->id,
            'direccion' => 'out',
            'from_phone' => $integracion->phoneE164(),
            'to_phone' => $from,
            'body' => $resultado['texto'],
            'producto_ids' => $resultado['producto_ids'],
            'handoff' => $resultado['handoff'],
            'status' => $ok ? 'ok' : 'error',
            'raw' => $enviado,
        ]);

        $mensaje->update([
            'respuesta' => $resultado['texto'],
            'producto_ids' => $resultado['producto_ids'],
            'handoff' => $resultado['handoff'],
            'status' => $ok ? 'ok' : 'error',
        ]);
    }

    private function extraerTexto(array $inbound): string
    {
        $type = (string) ($inbound['type'] ?? 'text');

        return match ($type) {
            'text' => trim((string) data_get($inbound, 'text.body', '')),
            'button' => trim((string) data_get($inbound, 'button.text', data_get($inbound, 'button.payload', ''))),
            'interactive' => trim((string) (
                data_get($inbound, 'interactive.button_reply.title')
                ?? data_get($inbound, 'interactive.list_reply.title')
                ?? ''
            )),
            default => '',
        };
    }
}
