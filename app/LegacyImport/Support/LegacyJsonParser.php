<?php

namespace App\LegacyImport\Support;

class LegacyJsonParser
{
    /** @return list<array<string, mixed>> */
    public static function productos(mixed $raw): array
    {
        $items = self::decode($raw);
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? $item['id_producto'] ?? 0);
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($id <= 0 || $cantidad <= 0) {
                continue;
            }

            $precio = (float) ($item['precio'] ?? $item['precio_venta'] ?? 0);
            $total = isset($item['total']) && $item['total'] !== '' ? (float) $item['total'] : $cantidad * $precio;

            $out[] = [
                'id_producto' => $id,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'precio_compra' => (float) ($item['precio_compra'] ?? 0),
                'descripcion' => (string) ($item['descripcion'] ?? $item['nombre'] ?? ''),
                'total' => $total,
            ];
        }

        return $out;
    }

    /** @return list<array{tipo: string, importe: float}> */
    public static function metodosPago(mixed $raw): array
    {
        $items = self::decode($raw);
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $out[] = ['tipo' => $item, 'importe' => 0.0];
                continue;
            }
            if (! is_array($item)) {
                continue;
            }

            $tipo = (string) ($item['tipo'] ?? $item['medio'] ?? $item['nombre'] ?? 'otro');
            $importe = (float) ($item['importe'] ?? $item['monto'] ?? $item['total'] ?? 0);
            $out[] = ['tipo' => $tipo, 'importe' => $importe];
        }

        return $out;
    }

    /** @return list<array{stkProd: string, det: string}> */
    public static function almacenes(mixed $raw): array
    {
        $items = self::decode($raw);
        if (! is_array($items)) {
            return [['stkProd' => 'stock', 'det' => 'Principal']];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $stk = (string) ($item['stkProd'] ?? $item['stock'] ?? '');
            $det = (string) ($item['det'] ?? $item['nombre'] ?? $stk);
            if ($stk !== '') {
                $out[] = ['stkProd' => $stk, 'det' => $det ?: $stk];
            }
        }

        return $out ?: [['stkProd' => 'stock', 'det' => 'Principal']];
    }

    /** @return list<int> */
    public static function puntosVenta(mixed $raw): array
    {
        $items = self::decode($raw);
        if (! is_array($items)) {
            return [1];
        }

        $nums = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $nums[] = (int) $item;
            } elseif (is_array($item)) {
                $n = $item['numero'] ?? $item['pto'] ?? $item['id'] ?? null;
                if ($n !== null && is_numeric($n)) {
                    $nums[] = (int) $n;
                }
            }
        }

        return $nums ?: [1];
    }

    private static function decode(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '' || $raw === '[]' || $raw === 'null') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }
}
