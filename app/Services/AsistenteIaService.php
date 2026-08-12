<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\IaMensaje;
use App\Models\Producto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsistenteIaService
{
    public function __construct(
        private IaCupoService $cupo,
        private N8nService $n8n,
    ) {}

    /** @return array{ok: bool, texto: string, cupo: array} */
    public function preguntar(int $empresaId, string $mensaje, ?int $userId = null, string $origen = 'panel'): array
    {
        $mensaje = trim($mensaje);
        $resumen = $this->cupo->resumen($empresaId);

        if ($mensaje === '') {
            return ['ok' => false, 'texto' => 'Escribí una pregunta.', 'cupo' => $resumen];
        }

        if (! $this->cupo->consumir($empresaId)) {
            return [
                'ok' => false,
                'texto' => $this->cupo->mensajeLimite($empresaId),
                'cupo' => $this->cupo->resumen($empresaId),
                'limite' => true,
            ];
        }

        IaMensaje::query()->create([
            'empresa_id' => $empresaId,
            'user_id' => $userId,
            'origen' => $origen,
            'rol' => 'user',
            'body' => $mensaje,
            'cuenta_cupo' => true,
        ]);

        $texto = $this->generar($empresaId, $mensaje);

        IaMensaje::query()->create([
            'empresa_id' => $empresaId,
            'user_id' => $userId,
            'origen' => $origen,
            'rol' => 'assistant',
            'body' => $texto,
            'cuenta_cupo' => false,
        ]);

        $this->n8n->emitir($empresaId, 'asistente.mensaje', [
            'origen' => $origen,
            'pregunta' => $mensaje,
            'respuesta' => $texto,
        ]);

        return [
            'ok' => true,
            'texto' => $texto,
            'cupo' => $this->cupo->resumen($empresaId),
        ];
    }

    private function generar(int $empresaId, string $mensaje): string
    {
        $productos = Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where(function ($q) use ($mensaje) {
                $tokens = preg_split('/\s+/', mb_strtolower($mensaje)) ?: [];
                foreach (array_slice($tokens, 0, 6) as $token) {
                    if (mb_strlen($token) < 3) {
                        continue;
                    }
                    $like = '%'.$token.'%';
                    $q->orWhere('nombre', 'like', $like)->orWhere('codigo', 'like', $like);
                }
            })
            ->limit(8)
            ->get(['id', 'codigo', 'nombre', 'precio_venta']);

        if ($productos->isEmpty()) {
            $productos = Producto::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->limit(8)
                ->get(['id', 'codigo', 'nombre', 'precio_venta']);
        }

        $apiKey = (string) config('ycloud.openai_api_key');
        if ($apiKey === '') {
            if ($productos->isEmpty()) {
                return 'No tengo API de IA configurada ni productos para armar una respuesta. Configurá OPENAI_API_KEY o cargá productos.';
            }

            $lineas = ['Según el catálogo:'];
            foreach ($productos as $p) {
                $lineas[] = '• '.$p->nombre.' ('.$p->codigo.') $ '.number_format((float) $p->precio_venta, 2, ',', '.');
            }

            return implode("\n", $lineas);
        }

        $empresa = Empresa::query()->find($empresaId);
        $nombre = $empresa?->nombre_fantasia ?: $empresa?->razon_social ?: 'la tienda';
        $base = rtrim((string) config('ycloud.openai_base_url'), '/');

        try {
            $response = Http::timeout(25)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($base.'/chat/completions', [
                    'model' => config('ycloud.openai_model'),
                    'temperature' => 0.3,
                    'max_tokens' => 400,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Asistente de {$nombre} (POSMoon). Español, breve. "
                                .'Usá el catálogo JSON si habla de productos. No inventes stock ni precios. '
                                .'No des consejos legales/impositivos definitivos.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $mensaje."\n\nCatálogo:\n".json_encode($productos, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            $texto = trim((string) data_get($response->json(), 'choices.0.message.content'));

            return $texto !== '' ? $texto : 'No pude generar una respuesta. Probá de nuevo.';
        } catch (\Throwable $e) {
            Log::warning('Asistente IA: '.$e->getMessage());

            return 'El servicio de IA no respondió. Reintentá en un momento.';
        }
    }
}
