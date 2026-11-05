<?php

namespace App\Billing\Jobs;

use App\Billing\Models\WebhookEvent;
use App\Billing\Services\MercadoPagoClient;
use App\Billing\Services\PaymentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessMercadoPagoNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public int $eventId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 3600];
    }

    public function handle(PaymentProcessor $processor, MercadoPagoClient $mp): void
    {
        $evento = WebhookEvent::find($this->eventId);
        if (! $evento || $evento->procesado_at) {
            return;
        }

        $evento->increment('intentos');

        try {
            $ids = match ($evento->topic) {
                'payment' => [$evento->resource_id],
                'merchant_order' => array_map(
                    fn ($p) => (string) $p['id'],
                    $mp->merchantOrder((string) $evento->resource_id)['payments'] ?? [],
                ),
                default => [],
            };

            $resultados = [];
            foreach (array_filter($ids) as $id) {
                $resultados[$id] = $processor->procesarMercadoPago((string) $id, 'webhook')['resultado'];
            }

            $evento->update(['procesado_at' => now(), 'error' => $resultados ? json_encode($resultados) : null]);
        } catch (Throwable $e) {
            $evento->update(['error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }
}
