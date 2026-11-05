<?php

namespace App\Billing\Services;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Checkout;
use App\Billing\Models\Customer;
use App\Billing\Support\Money;
use DomainException;
use Illuminate\Support\Str;
use Throwable;

class CheckoutService
{
    public function __construct(
        private CustomerStatusService $status,
        private MercadoPagoClient $mp,
    ) {}

    /**
     * Importe a cobrar según el modo: "total" (toda la deuda) o "periodo" (solo la cuota del mes).
     *
     * @return array{base: int, recargo: int, total: int, periodo: string}
     */
    public function cotizar(Customer $c, string $modo): array
    {
        $estado = $this->status->estado($c);
        $saldo = $estado['saldo'];

        $base = $modo === 'periodo' && $estado['cuota_periodo'] > 0
            ? min($saldo, $estado['cuota_periodo'])
            : $saldo;

        return [
            'base' => max(0, $base),
            'recargo' => $base > 0 ? $estado['recargo'] : 0,
            'total' => max(0, $base) + ($base > 0 ? $estado['recargo'] : 0),
            'periodo' => $estado['periodo'],
        ];
    }

    /** Devuelve un checkout listo (reutiliza el abierto si coincide el importe). */
    public function obtener(Customer $c, string $modo = 'total'): Checkout
    {
        $modo = in_array($modo, ['total', 'periodo'], true) ? $modo : 'total';
        $q = $this->cotizar($c, $modo);

        if ($q['total'] < 100) {
            throw new DomainException('No hay importe pendiente de pago.');
        }

        $abierto = Checkout::where('customer_id', $c->id)
            ->where('estado', 'abierto')
            ->where('modo', $modo)
            ->where('importe_centavos', $q['total'])
            ->where('recargo_centavos', $q['recargo'])
            ->whereNotNull('init_point')
            ->where('expira_at', '>', now()->addHour())
            ->latest('id')
            ->first();

        if ($abierto) {
            return $abierto;
        }

        $ttl = (int) config('billing.mercadopago.checkout_ttl_horas', 48);
        $checkout = Checkout::create([
            'customer_id' => $c->id,
            'external_reference' => Checkout::PREFIJO.Str::uuid(),
            'modo' => $modo,
            'importe_centavos' => $q['total'],
            'recargo_centavos' => $q['recargo'],
            'recargo_periodo' => $q['recargo'] > 0 ? $q['periodo'] : null,
            'detalle' => $q,
            'estado' => 'abierto',
            'expira_at' => now()->addHours($ttl),
        ]);

        try {
            $pref = $this->mp->crearPreferencia($this->preferencia($c, $checkout, $q), $checkout->external_reference);
        } catch (Throwable $e) {
            $checkout->update(['estado' => 'error']);
            AuditLog::registrar('checkout_error', ['checkout' => $checkout->id, 'error' => $e->getMessage()], $c->id, 'error');
            throw $e;
        }

        $checkout->update([
            'preference_id' => $pref['id'],
            'init_point' => $pref['init_point'] ?? $pref['sandbox_init_point'] ?? null,
        ]);

        return $checkout;
    }

    private function preferencia(Customer $c, Checkout $checkout, array $q): array
    {
        $url = config('billing.public_url');
        $items = [[
            'id' => 'abono',
            'title' => mb_substr('Abono POSMoon - '.$c->nombre, 0, 250),
            'quantity' => 1,
            'currency_id' => 'ARS',
            'unit_price' => Money::pesos($q['base']),
        ]];
        if ($q['recargo'] > 0) {
            $items[] = [
                'id' => 'recargo',
                'title' => 'Recargo por mora '.$q['periodo'],
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => Money::pesos($q['recargo']),
            ];
        }

        $pref = [
            'items' => $items,
            'external_reference' => $checkout->external_reference,
            'metadata' => ['customer_id' => $c->id, 'checkout_id' => $checkout->id],
            'notification_url' => $url.'/webhooks/mercadopago?source_news=webhooks',
            'back_urls' => [
                'success' => $url.'/pagar/'.$c->pay_token.'/resultado',
                'pending' => $url.'/pagar/'.$c->pay_token.'/resultado',
                'failure' => $url.'/pagar/'.$c->pay_token.'/resultado',
            ],
            'auto_return' => 'approved',
            'binary_mode' => false,
            'expires' => true,
            'expiration_date_to' => $checkout->expira_at->toIso8601String(),
            'statement_descriptor' => config('billing.mercadopago.statement_descriptor'),
            'payment_methods' => [
                'installments' => max(1, (int) config('billing.mercadopago.max_cuotas', 3)),
                'default_installments' => 1,
            ],
        ];

        if ($c->email && filter_var($c->email, FILTER_VALIDATE_EMAIL)) {
            $pref['payer'] = ['email' => $c->email];
        }

        return $pref;
    }
}
