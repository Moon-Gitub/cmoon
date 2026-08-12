<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\YcloudConversacion;
use App\Models\YcloudIntegracion;
use App\Models\YcloudMensaje;
use App\Services\ProductoConsultaIaService;
use App\Services\YCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class YcloudController extends Controller
{
    public function index(): View
    {
        $empresaId = auth()->user()->empresa_id;
        $integracion = YcloudIntegracion::query()->where('empresa_id', $empresaId)->first();

        $stats = [
            'whatsapp' => Producto::query()->where('empresa_id', $empresaId)->where('publicar_whatsapp', true)->count(),
            'shopify' => Producto::query()->where('empresa_id', $empresaId)->where('publicar_shopify', true)->count(),
            'tiendanube' => Producto::query()->where('empresa_id', $empresaId)->where('publicar_tiendanube', true)->count(),
            'handoff' => $integracion
                ? YcloudConversacion::query()
                    ->where('integracion_id', $integracion->id)
                    ->where('handoff_until', '>', now())
                    ->count()
                : 0,
        ];

        $mensajes = $integracion
            ? $integracion->mensajes()->latest()->limit(20)->get()
            : collect();

        return view('ycloud.index', compact('integracion', 'stats', 'mensajes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $data = $request->validate([
            'api_key' => ['required', 'string', 'max:255'],
            'phone_from' => ['required', 'string', 'max:32'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'catalog_template' => ['nullable', 'string', 'max:128'],
        ]);

        YcloudIntegracion::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'api_key' => $data['api_key'],
                'phone_from' => $data['phone_from'],
                'waba_id' => $data['waba_id'] ?? null,
                'webhook_secret' => $data['webhook_secret'] ?? null,
                'catalog_template' => $data['catalog_template'] ?? null,
                'activo' => true,
                'bot_activo' => true,
                'auto_reply' => true,
            ]
        );

        return back()->with('ok', 'WhatsApp (YCloud) conectado.');
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $integracion = $this->integracionActual();

        $integracion->update([
            'bot_activo' => $request->boolean('bot_activo'),
            'auto_reply' => $request->boolean('auto_reply'),
            'catalog_template' => $request->input('catalog_template') ?: $integracion->catalog_template,
        ]);

        return back()->with('ok', 'Configuración del bot actualizada.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->integracionActual()->update(['activo' => false, 'bot_activo' => false]);

        return back()->with('ok', 'WhatsApp desconectado. Los flags de productos se mantienen.');
    }

    public function testConnection(YCloudService $ycloud): RedirectResponse
    {
        $integracion = $this->integracionActual();

        try {
            $balance = $ycloud->forIntegracion($integracion)->getBalance();
            if (isset($balance['error'])) {
                return back()->with('error', 'YCloud respondió error. Revisá la API key.');
            }

            return back()->with('ok', 'Conexión OK con YCloud.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo conectar: '.$e->getMessage());
        }
    }

    public function probar(Request $request, ProductoConsultaIaService $consultas): RedirectResponse
    {
        $texto = $request->validate([
            'consulta' => ['required', 'string', 'max:500'],
        ])['consulta'];

        $resultado = $consultas->responder((int) auth()->user()->empresa_id, $texto);

        return back()->with('prueba', $resultado);
    }

    public function mensajes(): View
    {
        $integracion = $this->integracionActual();

        return view('ycloud.mensajes', [
            'integracion' => $integracion,
            'mensajes' => $integracion->mensajes()->latest()->paginate(40),
            'conversaciones' => $integracion->conversaciones()->latest('last_inbound_at')->limit(30)->get(),
        ]);
    }

    public function reanudarBot(YcloudConversacion $conversacion): RedirectResponse
    {
        $integracion = $this->integracionActual();
        abort_unless($conversacion->integracion_id === $integracion->id, 404);

        $conversacion->update(['handoff_until' => null]);

        return back()->with('ok', 'El bot vuelve a responder a '.$conversacion->telefono);
    }

    private function integracionActual(): YcloudIntegracion
    {
        $integracion = YcloudIntegracion::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($integracion, 404);

        return $integracion;
    }
}
