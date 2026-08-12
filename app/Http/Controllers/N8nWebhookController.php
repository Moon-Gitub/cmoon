<?php

namespace App\Http\Controllers;

use App\Models\N8nIntegracion;
use App\Models\N8nLog;
use App\Models\Producto;
use App\Services\YCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) ($request->header('X-N8N-Secret') ?: $request->header('X-POSMoon-Secret') ?: '');
        $empresaId = (int) ($request->input('empresa_id') ?: $request->header('X-Empresa-Id'));

        if ($secret === '') {
            return response()->json(['ok' => false, 'error' => 'missing secret'], 401);
        }

        $integracion = N8nIntegracion::withoutGlobalScopes()
            ->where('activo', true)
            ->whereNotNull('inbound_secret')
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->get()
            ->first(fn (N8nIntegracion $int) => hash_equals((string) $int->inbound_secret, $secret));

        if (! $integracion) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $accion = (string) $request->input('accion', 'ping');

        N8nLog::query()->create([
            'integracion_id' => $integracion->id,
            'evento' => 'inbound.'.$accion,
            'direccion' => 'in',
            'payload' => $request->except(['header_value']),
            'status' => 'ok',
        ]);

        $data = match ($accion) {
            'ping' => ['pong' => true, 'empresa_id' => $integracion->empresa_id],
            'productos.buscar' => $this->buscarProductos($integracion->empresa_id, (string) $request->input('q', '')),
            'whatsapp.enviar' => $this->enviarWhatsapp($integracion->empresa_id, $request, app(YCloudService::class)),
            default => ['error' => 'accion desconocida', 'acciones' => ['ping', 'productos.buscar', 'whatsapp.enviar']],
        };

        return response()->json(['ok' => true, 'accion' => $accion, 'data' => $data]);
    }

    private function buscarProductos(int $empresaId, string $q): array
    {
        $query = Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(fn ($b) => $b->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like));
        }

        return $query->limit(20)->get(['id', 'codigo', 'nombre', 'precio_venta'])->all();
    }

    private function enviarWhatsapp(int $empresaId, Request $request, YCloudService $ycloud): array
    {
        $integracion = \App\Models\YcloudIntegracion::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if (! $integracion) {
            return ['error' => 'WhatsApp no conectado'];
        }

        $to = (string) $request->input('to');
        $body = (string) $request->input('body');
        if ($to === '' || $body === '') {
            return ['error' => 'to y body son obligatorios'];
        }

        return $ycloud->forIntegracion($integracion)->sendText($to, $body);
    }
}
