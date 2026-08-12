<?php

namespace App\Http\Controllers;

use App\Jobs\WhatsApp\ResponderConsultaWhatsApp;
use App\Models\YcloudIntegracion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class YcloudWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $type = (string) ($payload['type'] ?? '');

        if ($type !== 'whatsapp.inbound_message.received') {
            return response('ignored', 200);
        }

        $inbound = $payload['whatsappInboundMessage'] ?? null;
        if (! is_array($inbound)) {
            return response('missing inbound', 200);
        }

        $to = (string) ($inbound['to'] ?? '');
        $integracion = $this->resolverIntegracion($request, $to);

        if (! $integracion) {
            Log::info('YCloud webhook sin integración', ['to' => $to]);

            return response('no integration', 200);
        }

        if ($secret = $integracion->webhook_secret ?: config('ycloud.webhook_secret')) {
            if (! $this->firmaValida($request, (string) $secret)) {
                return response('invalid signature', 401);
            }
        }

        ResponderConsultaWhatsApp::dispatch($integracion->id, $inbound);

        return response('ok', 200);
    }

    private function resolverIntegracion(Request $request, string $to): ?YcloudIntegracion
    {
        $normalizado = preg_replace('/[^\d+]/', '', $to) ?? $to;

        $candidatas = YcloudIntegracion::query()->where('activo', true)->get();

        foreach ($candidatas as $int) {
            $from = preg_replace('/[^\d+]/', '', $int->phoneE164()) ?? '';
            if ($from !== '' && ($from === $normalizado || ltrim($from, '+') === ltrim($normalizado, '+'))) {
                return $int;
            }
        }

        if ($candidatas->count() === 1) {
            return $candidatas->first();
        }

        return null;
    }

    private function firmaValida(Request $request, string $secret): bool
    {
        $header = (string) ($request->header('X-YCloud-Signature')
            ?: $request->header('YCloud-Signature')
            ?: '');

        if ($header === '') {
            return true;
        }

        $esperado = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($esperado, $header) || hash_equals('sha256='.$esperado, $header);
    }
}
