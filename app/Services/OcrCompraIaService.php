<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class OcrCompraIaService
{
    public function __construct(
        private IaLlmService $llm,
        private IaCupoService $cupo,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   proveedor?: array{nombre?: string, cuit?: string}|null,
     *   items: list<array{descripcion: string, cantidad: float, costo_unitario: float, codigo?: string|null}>,
     *   total?: float|null,
     *   fecha?: string|null,
     *   factura_numero?: string|null,
     *   texto?: string,
     *   cupo?: array,
     *   limite?: bool
     * }
     */
    public function parsearTexto(int $empresaId, string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [
                'ok' => false,
                'items' => [],
                'texto' => 'Pegá el texto de la factura o remito.',
                'cupo' => $this->cupo->resumen($empresaId),
            ];
        }

        $system = 'Sos un extractor de facturas de compra para un POS argentino. '
            .'Respondé SOLO JSON válido sin markdown, con esta forma: '
            .'{"proveedor":{"nombre":"...","cuit":"..."},'
            .'"fecha":"YYYY-MM-DD","factura_numero":"...","total":0,'
            .'"items":[{"descripcion":"...","cantidad":1,"costo_unitario":0,"codigo":null}]}. '
            .'Si un dato no está, usá null. Cantidades y costos numéricos.';

        $user = "Empresa #{$empresaId}. Texto OCR/factura:\n".$texto;

        $res = $this->llm->completar($empresaId, $system, $user, 800);

        if (! ($res['ok'] ?? false)) {
            return [
                'ok' => false,
                'items' => [],
                'texto' => $res['texto'] ?? 'No se pudo interpretar.',
                'cupo' => $res['cupo'] ?? $this->cupo->resumen($empresaId),
                'limite' => $res['limite'] ?? false,
            ];
        }

        $parsed = $this->decodeJson($res['texto']);
        if ($parsed === null) {
            Log::warning('OcrCompraIa: JSON inválido', ['raw' => $res['texto']]);

            return [
                'ok' => false,
                'items' => [],
                'texto' => 'La IA no devolvió un JSON usable. Reintentá.',
                'cupo' => $res['cupo'],
            ];
        }

        $items = [];
        foreach ($parsed['items'] ?? [] as $item) {
            $items[] = [
                'descripcion' => (string) ($item['descripcion'] ?? 'Ítem'),
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'costo_unitario' => (float) ($item['costo_unitario'] ?? 0),
                'codigo' => isset($item['codigo']) ? (string) $item['codigo'] : null,
            ];
        }

        return [
            'ok' => true,
            'proveedor' => $parsed['proveedor'] ?? null,
            'items' => $items,
            'total' => isset($parsed['total']) ? (float) $parsed['total'] : null,
            'fecha' => $parsed['fecha'] ?? null,
            'factura_numero' => $parsed['factura_numero'] ?? null,
            'cupo' => $res['cupo'],
        ];
    }

    private function decodeJson(string $raw): ?array
    {
        $raw = trim($raw);
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
