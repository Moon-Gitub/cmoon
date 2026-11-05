<?php

namespace App\Billing\Services;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Checkout;
use App\Billing\Models\Customer;
use App\Billing\Models\Payment;
use App\Billing\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Único punto autorizado para acreditar o revertir pagos en el libro mayor.
 */
class PaymentProcessor
{
    public const REVERSIBLES = ['refunded', 'charged_back', 'cancelled'];

    public function __construct(
        private MercadoPagoClient $mp,
        private LedgerService $ledger,
    ) {}

    /**
     * @return array{resultado: string, customer_id: ?int, importe: int, estado_mp: ?string}
     */
    public function procesarMercadoPago(string $paymentId, string $origen): array
    {
        $paymentId = trim($paymentId);
        if (! preg_match('/^\d{5,30}$/', $paymentId)) {
            return $this->fin('id_invalido', $origen, $paymentId);
        }

        $pago = $this->mp->payment($paymentId);
        if ($pago === null) {
            return $this->fin('pago_inexistente', $origen, $paymentId);
        }

        $estadoMp = (string) ($pago['status'] ?? 'desconocido');
        $importe = Money::centavos((float) ($pago['transaction_amount'] ?? 0));

        $collector = (string) config('billing.mercadopago.collector_id');
        if ($collector !== '' && (string) ($pago['collector_id'] ?? '') !== $collector) {
            return $this->fin('collector_ajeno', $origen, $paymentId, null, $importe, $estadoMp, 'warning');
        }
        if (($pago['currency_id'] ?? 'ARS') !== 'ARS') {
            return $this->fin('moneda_invalida', $origen, $paymentId, null, $importe, $estadoMp, 'warning');
        }
        if (($pago['live_mode'] ?? true) === false && ! config('billing.mercadopago.aceptar_sandbox')) {
            return $this->fin('sandbox_rechazado', $origen, $paymentId, null, $importe, $estadoMp, 'warning');
        }

        $checkout = Checkout::esReferenciaPropia($pago['external_reference'] ?? null)
            ? Checkout::with('customer')->where('external_reference', $pago['external_reference'])->first()
            : null;

        if (! $checkout) {
            return $this->fin('referencia_desconocida', $origen, $paymentId, null, $importe, $estadoMp);
        }

        $payment = $this->guardarPago($pago, $checkout, $importe);
        $customer = $checkout->customer;

        if ($estadoMp === 'approved') {
            return $this->acreditar($customer, $checkout, $payment, $pago, $importe, $origen);
        }

        if (in_array($estadoMp, self::REVERSIBLES, true) && $this->ledger->existe("mp:{$paymentId}")) {
            return $this->revertir($customer, $payment, $importe, $estadoMp, $origen);
        }

        return $this->fin('no_aprobado', $origen, $paymentId, $customer->id, $importe, $estadoMp);
    }

    private function acreditar(Customer $customer, Checkout $checkout, Payment $payment, array $pago, int $importe, string $origen): array
    {
        $id = (string) $pago['id'];
        if ($importe <= 0) {
            return $this->fin('importe_invalido', $origen, $id, $customer->id, $importe, 'approved', 'warning');
        }

        $fecha = $this->fecha($pago);

        try {
            $acreditado = DB::transaction(function () use ($customer, $checkout, $payment, $importe, $id, $fecha, $origen) {
                Customer::whereKey($customer->id)->lockForUpdate()->first();

                if ($this->ledger->existe("mp:{$id}")) {
                    return false;
                }

                if ($checkout->recargo_centavos > 0 && $checkout->recargo_periodo) {
                    $this->ledger->registrar(
                        $customer, 'recargo', $checkout->recargo_centavos,
                        CustomerStatusService::referenciaRecargo($customer, $checkout->recargo_periodo),
                        "Recargo por mora {$checkout->recargo_periodo} (Op. {$id})",
                        $origen, $fecha, $payment->id,
                    );
                }

                $asiento = $this->ledger->registrar(
                    $customer, 'pago', -$importe, "mp:{$id}",
                    "Pago Mercado Pago (Op. {$id})", $origen, $fecha, $payment->id,
                );
                if (! $asiento) {
                    return false;
                }

                $payment->update(['acreditado_at' => now()]);
                $checkout->update(['estado' => 'pagado', 'pagado_at' => now()]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $acreditado = false;
        }

        if (! $acreditado) {
            return $this->fin('duplicado_ignorado', $origen, $id, $customer->id, $importe, 'approved');
        }

        if ($importe !== $checkout->importe_centavos) {
            AuditLog::registrar('importe_distinto_checkout', [
                'payment_id' => $id,
                'pagado' => $importe,
                'esperado' => $checkout->importe_centavos,
            ], $customer->id, 'warning');
        }

        $this->postAcreditacion($customer, $payment);

        return $this->fin('acreditado', $origen, $id, $customer->id, $importe, 'approved');
    }

    private function revertir(Customer $customer, Payment $payment, int $importe, string $estadoMp, string $origen): array
    {
        $id = $payment->external_id;
        $asiento = $this->ledger->registrar(
            $customer, 'reverso', $importe, "mp-reverso:{$id}",
            "Reverso Mercado Pago ({$estadoMp}) Op. {$id}", $origen, now(), $payment->id,
        );

        return $this->fin($asiento ? 'revertido' : 'reverso_duplicado', $origen, $id, $customer->id, $importe, $estadoMp, $asiento ? 'alerta' : 'info');
    }

    /**
     * Transferencia, efectivo o bonificación registrada por el admin.
     * La referencia externa (nro de operación bancaria) evita cargar dos veces la misma.
     */
    public function registrarManual(Customer $customer, string $metodo, int $importe, CarbonInterface $fecha, ?string $referenciaExterna, ?string $nota, ?int $userId): ?Payment
    {
        $externalId = $referenciaExterna ? Str::limit(trim($referenciaExterna), 90, '') : (string) Str::uuid();
        $tipo = $metodo === 'bonificacion' ? 'bonificacion' : 'pago';

        $payment = DB::transaction(function () use ($customer, $metodo, $importe, $fecha, $externalId, $nota, $userId, $tipo) {
            Customer::whereKey($customer->id)->lockForUpdate()->first();

            if (Payment::where('proveedor', $metodo)->where('external_id', $externalId)->exists()) {
                return null;
            }

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'proveedor' => $metodo,
                'external_id' => $externalId,
                'estado' => 'approved',
                'importe_centavos' => $importe,
                'metodo' => $metodo,
                'aprobado_at' => $fecha,
                'acreditado_at' => now(),
                'payload' => ['nota' => $nota, 'user_id' => $userId],
            ]);

            $this->ledger->registrar(
                $customer, $tipo, -$importe, "manual:{$metodo}:{$externalId}",
                trim(ucfirst($metodo).' '.($nota ?? '')), 'admin', $fecha, $payment->id, userId: $userId,
            );

            return $payment;
        });

        AuditLog::registrar($payment ? 'pago_manual' : 'pago_manual_duplicado', [
            'metodo' => $metodo, 'importe' => $importe, 'referencia' => $externalId,
        ], $customer->id, $payment ? 'info' : 'warning');

        if ($payment && $tipo === 'pago') {
            $this->postAcreditacion($customer, $payment);
        }

        return $payment;
    }

    public function ajustar(Customer $customer, int $importe, string $motivo, ?int $userId): void
    {
        $this->ledger->registrar(
            $customer, 'ajuste', $importe, 'ajuste:'.Str::uuid(), 'Ajuste: '.$motivo, 'admin', now(), userId: $userId,
        );
        AuditLog::registrar('ajuste', ['importe' => $importe, 'motivo' => $motivo], $customer->id, 'warning');
    }

    private function postAcreditacion(Customer $customer, Payment $payment): void
    {
        try {
            app(DunningService::class)->enviarRecibo($customer, $payment);
        } catch (Throwable $e) {
            AuditLog::registrar('recibo_error', ['payment' => $payment->id, 'error' => $e->getMessage()], $customer->id, 'warning');
        }
    }

    private function guardarPago(array $pago, Checkout $checkout, int $importe): Payment
    {
        $neto = isset($pago['transaction_details']['net_received_amount'])
            ? Money::centavos((float) $pago['transaction_details']['net_received_amount'])
            : null;

        $datos = [
            'customer_id' => $checkout->customer_id,
            'estado' => (string) $pago['status'],
            'estado_detalle' => $pago['status_detail'] ?? null,
            'importe_centavos' => $importe,
            'neto_centavos' => $neto,
            'metodo' => $pago['payment_method_id'] ?? null,
            'tipo_pago' => $pago['payment_type_id'] ?? null,
            'checkout_id' => $checkout->id,
            'aprobado_at' => isset($pago['date_approved']) ? Carbon::parse($pago['date_approved']) : null,
            'payload' => $pago,
        ];

        try {
            return Payment::updateOrCreate(['proveedor' => 'mercadopago', 'external_id' => (string) $pago['id']], $datos);
        } catch (UniqueConstraintViolationException) {
            $p = Payment::where('proveedor', 'mercadopago')->where('external_id', (string) $pago['id'])->firstOrFail();
            $p->update($datos);

            return $p;
        }
    }

    private function fecha(array $pago): CarbonInterface
    {
        foreach (['date_approved', 'date_created'] as $campo) {
            if (! empty($pago[$campo])) {
                return Carbon::parse($pago[$campo])->setTimezone(config('app.timezone'));
            }
        }

        return now();
    }

    private function fin(string $resultado, string $origen, string $paymentId, ?int $customerId = null, int $importe = 0, ?string $estadoMp = null, string $nivel = 'info'): array
    {
        AuditLog::registrar('mp_'.$resultado, [
            'payment_id' => $paymentId,
            'origen' => $origen,
            'importe' => $importe,
            'estado_mp' => $estadoMp,
        ], $customerId, $nivel);

        return ['resultado' => $resultado, 'customer_id' => $customerId, 'importe' => $importe, 'estado_mp' => $estadoMp];
    }
}
