<?php

namespace App\Billing\Http\Controllers;

use App\Billing\Models\Checkout;
use App\Billing\Models\Customer;
use App\Billing\Models\Payment;
use App\Billing\Services\CheckoutService;
use App\Billing\Services\CustomerStatusService;
use App\Billing\Services\LedgerService;
use App\Billing\Services\PaymentProcessor;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;

class PayPageController extends Controller
{
    public function show(string $token, CustomerStatusService $status, LedgerService $ledger, CheckoutService $checkouts): View
    {
        $c = $this->cliente($token);
        $estado = $status->estado($c);

        return view('billing.pay.show', [
            'customer' => $c,
            'estado' => $estado,
            'pendientes' => $ledger->cargosPendientes($c),
            'cotTotal' => $checkouts->cotizar($c, 'total'),
            'cotPeriodo' => $checkouts->cotizar($c, 'periodo'),
            'transferencia' => config('billing.transferencia'),
        ]);
    }

    public function ir(string $token, Request $request, CheckoutService $checkouts): RedirectResponse
    {
        $c = $this->cliente($token);

        try {
            $checkout = $checkouts->obtener($c, (string) $request->query('modo', 'total'));
        } catch (DomainException $e) {
            return redirect()->route('billing.pagar', $token)->with('error', $e->getMessage());
        } catch (Throwable) {
            return redirect()->route('billing.pagar', $token)
                ->with('error', 'Mercado Pago no respondió. Probá de nuevo en un minuto o pagá por transferencia.');
        }

        return redirect()->away($checkout->init_point);
    }

    /** Retorno desde Mercado Pago: nunca confía en la URL, verifica contra la API. */
    public function resultado(string $token, Request $request, PaymentProcessor $processor, CustomerStatusService $status): View
    {
        $c = $this->cliente($token);
        $paymentId = (string) ($request->query('payment_id') ?? $request->query('collection_id') ?? '');
        $ref = (string) $request->query('external_reference', '');

        $propio = $ref !== '' && Checkout::where('customer_id', $c->id)->where('external_reference', $ref)->exists();
        if ($propio && preg_match('/^\d{5,30}$/', $paymentId)) {
            try {
                $processor->procesarMercadoPago($paymentId, 'retorno');
            } catch (Throwable) {
                // El webhook y el barrido de pendientes lo reintentan.
            }
        }

        return view('billing.pay.result', [
            'customer' => $c,
            'paymentId' => $propio ? $paymentId : '',
            'pago' => $this->pagoDelCliente($c, $paymentId),
            'estado' => $status->estado($c),
        ]);
    }

    public function estado(string $token, Request $request, CustomerStatusService $status): JsonResponse
    {
        $c = $this->cliente($token);
        $pago = $this->pagoDelCliente($c, (string) $request->query('payment_id', ''));
        $estado = $status->estado($c);

        return response()->json([
            'pago' => $pago ? ['estado' => $pago->estado, 'acreditado' => (bool) $pago->acreditado_at] : null,
            'nivel' => $estado['nivel'],
            'saldo' => $estado['saldo'] / 100,
        ]);
    }

    private function pagoDelCliente(Customer $c, string $paymentId): ?Payment
    {
        return $paymentId === '' ? null : Payment::where('customer_id', $c->id)
            ->where('proveedor', 'mercadopago')->where('external_id', $paymentId)->first();
    }

    private function cliente(string $token): Customer
    {
        abort_unless(strlen($token) >= 32, 404);

        return Customer::where('pay_token', $token)->where('estado', '!=', 'baja')->firstOrFail();
    }
}
