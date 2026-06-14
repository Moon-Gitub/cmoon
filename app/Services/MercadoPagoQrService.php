<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Genera órdenes QR de Mercado Pago (Instore) para cobrar ventas del POS.
 */
class MercadoPagoQrService
{
    public function configurado(): bool
    {
        return filled(config('mercadopago.access_token'))
            && filled(config('mercadopago.user_id'))
            && filled(config('mercadopago.external_pos_id'));
    }

    /**
     * Crea una orden QR con importe fijo. Devuelve referencia externa y datos del QR.
     *
     * @return array{referencia: string, qr_data: string, qr_base64: ?string, order_id: ?string}
     */
    public function crearOrden(float $total, string $titulo, ?string $referencia = null): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException(
                'Mercado Pago no está configurado. Completá MERCADOPAGO_ACCESS_TOKEN, MERCADOPAGO_USER_ID y MERCADOPAGO_EXTERNAL_POS_ID en el servidor.'
            );
        }

        $referencia = $referencia ?? ('cmoon-'.Str::uuid());
        $userId = config('mercadopago.user_id');
        $posId = config('mercadopago.external_pos_id');

        $respuesta = Http::withToken((string) config('mercadopago.access_token'))
            ->acceptJson()
            ->put("https://api.mercadopago.com/instore/orders/qr/seller/collectors/{$userId}/pos/{$posId}/qrs", [
                'external_reference' => $referencia,
                'title' => Str::limit($titulo, 250),
                'description' => 'Venta CMoon POS',
                'total_amount' => round($total, 2),
                'items' => [[
                    'sku_number' => '1',
                    'category' => 'marketplace',
                    'title' => Str::limit($titulo, 250),
                    'description' => 'Venta POS',
                    'unit_price' => round($total, 2),
                    'quantity' => 1,
                    'unit_measure' => 'unit',
                    'total_amount' => round($total, 2),
                ]],
            ]);

        if (! $respuesta->successful()) {
            $detalle = $respuesta->json('message') ?? $respuesta->body();
            throw new RuntimeException("Mercado Pago no generó el QR: {$detalle}");
        }

        $datos = $respuesta->json();

        return [
            'referencia' => $referencia,
            'qr_data' => (string) ($datos['qr_data'] ?? ''),
            'qr_base64' => $datos['qr_data_base64'] ?? null,
            'order_id' => isset($datos['id']) ? (string) $datos['id'] : null,
        ];
    }

    /**
     * Consulta si la orden QR ya fue cobrada.
     *
     * @return array{aprobado: bool, estado: ?string, payment_id: ?string}
     */
    public function consultarPago(string $referencia): array
    {
        if (! filled(config('mercadopago.access_token'))) {
            return ['aprobado' => false, 'estado' => null, 'payment_id' => null];
        }

        $respuesta = Http::withToken((string) config('mercadopago.access_token'))
            ->acceptJson()
            ->get('https://api.mercadopago.com/v1/payments/search', [
                'external_reference' => $referencia,
                'sort' => 'date_created',
                'criteria' => 'desc',
            ]);

        if (! $respuesta->successful()) {
            return ['aprobado' => false, 'estado' => null, 'payment_id' => null];
        }

        $pago = collect($respuesta->json('results', []))->first();

        if (! $pago) {
            return ['aprobado' => false, 'estado' => 'pending', 'payment_id' => null];
        }

        $estado = (string) ($pago['status'] ?? '');

        return [
            'aprobado' => $estado === 'approved',
            'estado' => $estado,
            'payment_id' => isset($pago['id']) ? (string) $pago['id'] : null,
        ];
    }
}
