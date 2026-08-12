<?php

namespace App\Http\Controllers;

use App\Models\N8nIntegracion;
use App\Services\N8nService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class N8nController extends Controller
{
    public function index(): View
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = N8nIntegracion::query()->where('empresa_id', $empresaId)->first();
        $eventos = config('n8n.events');
        $logs = $integracion
            ? $integracion->logs()->latest()->limit(30)->get()
            : collect();

        return view('n8n.index', [
            'integracion' => $integracion,
            'eventos' => $eventos,
            'logs' => $logs,
            'inboundUrl' => url('/webhooks/n8n'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $eventos = array_keys(config('n8n.events'));

        $data = $request->validate([
            'base_url' => ['nullable', 'url', 'max:255'],
            'header_name' => ['nullable', 'string', 'max:64'],
            'header_value' => ['nullable', 'string', 'max:255'],
            'inbound_secret' => ['nullable', 'string', 'max:255'],
            'webhooks' => ['nullable', 'array'],
            'webhooks.*' => ['nullable', 'url', 'max:500'],
        ]);

        $webhooks = [];
        foreach ($eventos as $evento) {
            $url = trim((string) data_get($data, 'webhooks.'.$evento, ''));
            if ($url !== '') {
                $webhooks[$evento] = $url;
            }
        }

        $attrs = [
            'base_url' => $data['base_url'] ?? null,
            'header_name' => $data['header_name'] ?: 'X-N8N-Auth',
            'inbound_secret' => $data['inbound_secret'] ?? null,
            'webhooks' => $webhooks,
            'activo' => true,
        ];

        if (! empty($data['header_value'])) {
            $attrs['header_value'] = $data['header_value'];
        }

        N8nIntegracion::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            $attrs
        );

        return back()->with('ok', 'n8n conectado. Pegá las Production URL de cada Webhook node.');
    }

    public function probar(Request $request, N8nService $n8n): RedirectResponse
    {
        $integracion = N8nIntegracion::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->firstOrFail();

        $evento = $request->validate([
            'evento' => ['required', 'string', 'max:64'],
        ])['evento'];

        $resultado = $n8n->enviarAhora($integracion, $evento, [
            'test' => true,
            'mensaje' => 'Ping desde POSMoon',
        ]);

        return $resultado['ok'] ?? false
            ? back()->with('ok', 'n8n respondió OK ('.$evento.').')
            : back()->with('error', 'n8n no respondió: '.($resultado['mensaje'] ?? 'error'));
    }
}
