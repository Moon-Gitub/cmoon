<?php

namespace App\Billing\Http\Controllers;

use App\Billing\Jobs\ProcessMercadoPagoNotification;
use App\Billing\Models\WebhookEvent;
use App\Billing\Services\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function mercadopago(Request $request, WebhookSignature $firma): JsonResponse
    {
        $topic = (string) ($request->input('type') ?? $request->query('topic') ?? $request->input('topic') ?? '');
        $topic = $topic === 'topic_merchant_order_wh' ? 'merchant_order' : $topic;
        $resourceId = (string) ($request->query('data_id')
            ?? $request->input('data.id')
            ?? $request->query('id')
            ?? basename((string) $request->input('resource', '')));

        $valida = $firma->valida($request);

        $evento = WebhookEvent::create([
            'proveedor' => 'mercadopago',
            'topic' => $topic ?: null,
            'resource_id' => $resourceId ?: null,
            'request_id' => $request->header('x-request-id'),
            'firma_valida' => $valida,
            'payload' => $request->all(),
            'headers' => array_intersect_key($request->headers->all(), array_flip(['x-request-id', 'user-agent', 'content-type'])),
        ]);

        if (! $valida) {
            $evento->update(['error' => 'firma_invalida']);

            return response()->json(['ok' => false], 401);
        }

        if (in_array($topic, ['payment', 'merchant_order'], true) && $resourceId !== '') {
            ProcessMercadoPagoNotification::dispatch($evento->id);
        } else {
            $evento->update(['procesado_at' => now(), 'error' => 'topic_ignorado']);
        }

        return response()->json(['ok' => true]);
    }
}
