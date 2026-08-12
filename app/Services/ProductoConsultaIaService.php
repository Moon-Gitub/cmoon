<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductoConsultaIaService
{
    /** @return array{texto: string, producto_ids: array<int>, handoff: bool, quiere_catalogo: bool} */
    public function responder(int $empresaId, string $mensaje): array
    {
        $mensaje = trim($mensaje);
        $lower = mb_strtolower($mensaje);

        $quiereCatalogo = (bool) preg_match('/\b(catalogo|catálogo|lista|productos|ofertas|novedades)\b/u', $lower);
        $handoff = (bool) preg_match('/\b(humano|vendedor|persona|asesor|operador|hablar con alguien)\b/u', $lower);

        $productos = $this->buscar($empresaId, $mensaje);

        if ($handoff) {
            return [
                'texto' => 'En un momento te atiende alguien de la tienda. Si querés, dejá tu consulta y te respondemos por acá.',
                'producto_ids' => $productos->pluck('id')->all(),
                'handoff' => true,
                'quiere_catalogo' => false,
            ];
        }

        if ($quiereCatalogo && $productos->isEmpty()) {
            $productos = $this->destacados($empresaId);
        }

        if ($productos->isEmpty()) {
            return [
                'texto' => 'No encontré ese producto en el catálogo de WhatsApp. Probá con otro nombre o pedí “catálogo”. Si querés, te paso con una persona.',
                'producto_ids' => [],
                'handoff' => false,
                'quiere_catalogo' => $quiereCatalogo,
            ];
        }

        $textoIa = $this->completarConIa($empresaId, $mensaje, $productos);
        $texto = $textoIa ?: $this->respuestaPlantilla($productos, $quiereCatalogo);

        return [
            'texto' => $texto,
            'producto_ids' => $productos->pluck('id')->all(),
            'handoff' => false,
            'quiere_catalogo' => $quiereCatalogo,
        ];
    }

    public function buscar(int $empresaId, string $mensaje, int $limit = 8): Collection
    {
        $tokens = $this->tokens($mensaje);

        $query = Producto::withoutGlobalScopes()
            ->with('stocks')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('publicar_whatsapp', true);

        if ($tokens === []) {
            return collect();
        }

        $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $like = '%'.$token.'%';
                $q->orWhere('nombre', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('descripcion', 'like', $like);
            }
        });

        return $query->orderBy('nombre')->limit($limit)->get();
    }

    public function destacados(int $empresaId, int $limit = 8): Collection
    {
        return Producto::withoutGlobalScopes()
            ->with('stocks')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('publicar_whatsapp', true)
            ->where('precio_venta', '>', 0)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    private function respuestaPlantilla(Collection $productos, bool $catalogo): string
    {
        $lineas = $catalogo
            ? ["Estos productos están publicados en WhatsApp:"]
            : ["Encontré esto en el catálogo:"];

        foreach ($productos as $p) {
            $stock = $p->stockTotal();
            $disp = $stock > 0 ? 'stock '.rtrim(rtrim(number_format($stock, 2, ',', '.'), '0'), ',') : 'consultar stock';
            $lineas[] = '• '.$p->nombre.' — $ '.number_format((float) $p->precio_venta, 2, ',', '.').' ('.$disp.')';
        }

        $lineas[] = 'Si buscás otro modelo o talle, escribilo. Para hablar con una persona, decí “vendedor”.';

        return implode("\n", $lineas);
    }

    private function completarConIa(int $empresaId, string $mensaje, Collection $productos): ?string
    {
        $apiKey = (string) config('ycloud.openai_api_key');
        if ($apiKey === '') {
            return null;
        }

        $empresa = Empresa::query()->find($empresaId);
        $nombre = $empresa?->nombre_fantasia ?: $empresa?->razon_social ?: 'la tienda';

        $catalogo = $productos->map(function (Producto $p) {
            return [
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'precio' => (float) $p->precio_venta,
                'stock' => $p->stockTotal(),
            ];
        })->all();

        $base = rtrim((string) config('ycloud.openai_base_url'), '/');
        $model = (string) config('ycloud.openai_model');

        try {
            $response = Http::timeout(25)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($base.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.3,
                    'max_tokens' => 400,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Sos el asistente de ventas de {$nombre} por WhatsApp. "
                                .'Respondé en español, breve y claro. Solo usá los productos del JSON. '
                                .'No inventes stock ni precios. Si no está, decilo. '
                                .'No hables de Shopify ni del sistema interno.',
                        ],
                        [
                            'role' => 'user',
                            'content' => "Consulta del cliente:\n{$mensaje}\n\nProductos:\n".json_encode($catalogo, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI consulta WhatsApp falló', ['status' => $response->status()]);

                return null;
            }

            $texto = trim((string) data_get($response->json(), 'choices.0.message.content'));

            return $texto !== '' ? $texto : null;
        } catch (\Throwable $e) {
            Log::warning('OpenAI consulta WhatsApp: '.$e->getMessage());

            return null;
        }
    }

    /** @return list<string> */
    private function tokens(string $mensaje): array
    {
        $stop = ['el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'y', 'o', 'en', 'por', 'para', 'con', 'que', 'me', 'te', 'hay', 'tienen', 'tenes', 'tenés', 'precio', 'cuanto', 'cuánto', 'hola', 'buenas', 'buen', 'dia', 'día', 'tardes', 'catalogo', 'catálogo', 'lista', 'productos'];
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($mensaje)) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            if (mb_strlen($part) < 3 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }
}
