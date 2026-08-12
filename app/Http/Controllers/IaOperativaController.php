<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Services\IaLlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IaOperativaController extends Controller
{
    public function sugerirProducto(Request $request, IaLlmService $llm): JsonResponse
    {
        abort_unless(auth()->user()->can('productos.editar') || auth()->user()->can('productos.crear'), 403);

        $datos = $request->validate([
            'codigo' => ['nullable', 'string', 'max:80'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ]);

        $categorias = Categoria::query()->where('activa', true)->orderBy('nombre')->limit(80)->pluck('nombre')->all();
        $empresaId = (int) auth()->user()->empresa_id;

        $r = $llm->completar(
            $empresaId,
            'Sos un encargado de catálogo de un comercio argentino. Respondé SOLO JSON válido, sin markdown: '
            .'{"nombre":"...","descripcion":"...","categoria":"..."}. '
            .'Nombre comercial corto (incluí talle/color si se infiere). Descripción 1 línea. '
            .'categoria debe ser una de la lista o una propuesta breve. Español rioplatense.',
            'Código: '.($datos['codigo'] ?? '')."\nNombre: ".($datos['nombre'] ?? '')
            ."\nDescripción: ".($datos['descripcion'] ?? '')
            ."\nCategorías existentes: ".implode(', ', $categorias),
            250,
        );

        if (! ($r['ok'] ?? false)) {
            return response()->json($r, ! empty($r['limite']) ? 429 : 422);
        }

        $json = $this->parseJson($r['texto']);
        $catNombre = trim((string) ($json['categoria'] ?? ''));
        $categoriaId = null;
        if ($catNombre !== '') {
            $categoriaId = Categoria::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($catNombre)])
                ->value('id');
        }

        return response()->json([
            'ok' => true,
            'cupo' => $r['cupo'],
            'nombre' => $json['nombre'] ?? $datos['nombre'],
            'descripcion' => $json['descripcion'] ?? $datos['descripcion'],
            'categoria' => $catNombre,
            'categoria_id' => $categoriaId,
        ]);
    }

    public function explicarAfip(Request $request, IaLlmService $llm): JsonResponse
    {
        abort_unless(auth()->user()->can('facturacion.ver') || auth()->user()->can('pos.vender'), 403);

        $datos = $request->validate([
            'comprobante_id' => ['nullable', 'integer'],
            'mensaje' => ['nullable', 'string', 'max:4000'],
            'observaciones' => ['nullable', 'array'],
            'observaciones.*' => ['string', 'max:500'],
        ]);

        $textoError = trim((string) ($datos['mensaje'] ?? ''));
        if (! empty($datos['comprobante_id'])) {
            $c = Comprobante::query()->findOrFail($datos['comprobante_id']);
            $this->autorizarComprobante($c);
            $textoError = $c->mensaje_afip ?: $textoError;
            if ($textoError === '' && is_array($c->respuesta_afip)) {
                $textoError = json_encode($c->respuesta_afip, JSON_UNESCAPED_UNICODE);
            }
        }
        if (! empty($datos['observaciones'])) {
            $textoError = trim($textoError."\n".implode("\n", $datos['observaciones']));
        }

        if ($textoError === '') {
            return response()->json(['ok' => false, 'texto' => 'No hay mensaje de AFIP para explicar.'], 422);
        }

        $r = $llm->completar(
            (int) auth()->user()->empresa_id,
            'Explicá errores de AFIP/WSFE a un cajero en Argentina. 4–8 líneas. Qué significa, qué revisar '
            .'(CUIT, condición IVA, punto de venta, certificado, fecha) y el siguiente paso. Sin jerga innecesaria.',
            $textoError,
            350,
        );

        return response()->json([
            'ok' => $r['ok'],
            'texto' => $r['texto'],
            'cupo' => $r['cupo'] ?? null,
            'limite' => $r['limite'] ?? false,
        ], ! empty($r['limite']) ? 429 : 200);
    }

    /** Reglas (gratis, sin cupo IA): qué conviene publicar. */
    public function sugerirCanales(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $productos = Producto::with('stocks')->whereIn('id', $ids)->get();
        $sugeridos = [];

        foreach ($productos as $p) {
            $stock = $p->stockTotal();
            $precio = (float) $p->precio_venta;
            $nombreUtil = mb_strlen(trim((string) $p->nombre)) >= 4 && ! ctype_digit(trim((string) $p->nombre));
            $whatsapp = $p->activo && $precio > 0 && $stock > 0 && $nombreUtil;
            $shopify = $whatsapp;
            $tiendanube = $whatsapp;

            $sugeridos[] = [
                'id' => $p->id,
                'publicar_whatsapp' => $whatsapp,
                'publicar_shopify' => $shopify,
                'publicar_tiendanube' => $tiendanube,
                'motivo' => $whatsapp
                    ? 'Stock, precio y nombre usables'
                    : 'Falta stock, precio o un nombre comercial',
            ];
        }

        return response()->json(['ok' => true, 'gratis' => true, 'sugeridos' => $sugeridos]);
    }

    public function aplicarCanalesSugeridos(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $sugerencia = $this->sugerirCanales($request)->getData(true);
        $n = 0;
        foreach ($sugerencia['sugeridos'] ?? [] as $row) {
            Producto::query()->where('id', $row['id'])->update([
                'publicar_whatsapp' => (bool) $row['publicar_whatsapp'],
                'publicar_shopify' => (bool) $row['publicar_shopify'],
                'publicar_tiendanube' => (bool) $row['publicar_tiendanube'],
            ]);
            $n++;
        }

        return response()->json(['ok' => true, 'gratis' => true, 'actualizados' => $n]);
    }

    private function autorizarComprobante(Comprobante $c): void
    {
        $ventaEmpresa = $c->venta?->empresa_id;
        abort_unless(
            $ventaEmpresa === null || (int) $ventaEmpresa === (int) auth()->user()->empresa_id,
            403
        );
    }

    private function parseJson(string $texto): array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $texto) ?? $texto;
        $decoded = json_decode($texto, true);

        return is_array($decoded) ? $decoded : [];
    }
}
