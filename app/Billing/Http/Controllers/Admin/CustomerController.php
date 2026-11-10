<?php

namespace App\Billing\Http\Controllers\Admin;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Customer;
use App\Billing\Models\LedgerEntry;
use App\Billing\Models\LegacyDetalle;
use App\Billing\Models\Subscription;
use App\Billing\Services\CustomerStatusService;
use App\Billing\Services\DunningService;
use App\Billing\Services\LedgerService;
use App\Billing\Services\PaymentProcessor;
use App\Services\YCloudService;
use App\Billing\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerController extends Controller
{
    public function index(Request $request, LedgerService $ledger, CustomerStatusService $status): View
    {
        $q = trim((string) $request->query('q'));
        $saldos = $ledger->saldos();
        $hoy = CustomerStatusService::hoy();

        $clientes = Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%{$q}%")
                ->orWhere('dominio', 'like', "%{$q}%")->orWhere('cuit', 'like', "%{$q}%")
                ->orWhere('documento', 'like', "%{$q}%")->orWhere('telefono', 'like', "%{$q}%")))
            ->orderBy('nombre')
            ->get()
            ->map(fn (Customer $c) => [
                'c' => $c,
                'saldo' => $saldos[$c->id] ?? 0,
                'estado' => $status->estado($c, $hoy, $saldos[$c->id] ?? 0),
            ])
            ->sortByDesc('saldo')
            ->values();

        return view('billing.admin.clientes', compact('clientes', 'q'));
    }

    public function show(Customer $customer, LedgerService $ledger, CustomerStatusService $status): View
    {
        $estado = $status->estado($customer);
        $movimientos = $customer->ledger()->orderByDesc('fecha')->orderByDesc('id')->paginate(40);
        $ids = collect($movimientos->items())->map(fn (LedgerEntry $e) => LegacyDetalle::idDesdeReferencia($e->referencia))->filter();
        $cel = DunningService::celularArgentino((string) $customer->telefono);
        $mensaje = DunningService::textoLinkPago($customer, (int) $estado['saldo']);

        return view('billing.admin.cliente', [
            'c' => $customer,
            'estado' => $estado,
            'movimientos' => $movimientos,
            'detalles' => LegacyDetalle::whereIn('legacy_movimiento_id', $ids)->get()->keyBy('legacy_movimiento_id'),
            'suscripciones' => $customer->subscriptions()->get(),
            'pagos' => $customer->ledger()->whereIn('tipo', ['legacy_pago', 'pago', 'bonificacion'])->orderByDesc('fecha')->limit(20)->get(),
            'avisos' => $customer->notifications()->latest('id')->limit(20)->get(),
            'aging' => $ledger->aging($customer, CustomerStatusService::hoy()),
            'whatsapp' => $cel ? 'https://wa.me/'.$cel.'?text='.rawurlencode($mensaje) : null,
            'whatsappApi' => (bool) (config('ycloud.api_key') && config('ycloud.phone_from')),
        ]);
    }

    public function whatsapp(Customer $customer, CustomerStatusService $status): RedirectResponse
    {
        if (! config('ycloud.api_key') || ! config('ycloud.phone_from')) {
            return back()->with('error', 'Falta configurar YCloud. Usá el botón que abre WhatsApp.');
        }

        $cel = DunningService::celularArgentino((string) $customer->telefono);
        if (! $cel) {
            return back()->with('error', 'El teléfono no es un celular argentino.');
        }

        $estado = $status->estado($customer);
        $resp = YCloudService::make()->sendText($cel, DunningService::textoLinkPago($customer, (int) $estado['saldo']));
        if (isset($resp['error']) || (isset($resp['ok']) && ! $resp['ok'])) {
            return back()->with('error', 'WhatsApp no se pudo enviar. Abrí el chat con el botón.');
        }

        AuditLog::registrar('whatsapp_manual', ['destino' => $cel], $customer->id);

        return back()->with('ok', 'WhatsApp enviado.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'dominio' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'string', 'max:20'],
            'estado' => ['required', 'in:activo,baja'],
            'modo_cobro' => ['required', 'in:solo_aviso,bloqueo'],
            'aplicar_recargos' => ['nullable', 'boolean'],
            'prorroga_hasta' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);
        $datos['aplicar_recargos'] = $request->boolean('aplicar_recargos');

        $antes = $customer->only(array_keys($datos));
        $customer->update($datos);
        AuditLog::registrar('cliente_editado', ['antes' => $antes, 'despues' => $customer->only(array_keys($datos))], $customer->id);

        return back()->with('ok', 'Cliente actualizado.');
    }

    public function abono(Request $request, Customer $customer): RedirectResponse
    {
        $datos = $request->validate([
            'concepto' => ['required', 'string', 'max:120'],
            'importe' => ['required', 'string', 'max:20'],
            'desde' => ['required', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'subscription_id' => ['nullable', 'integer'],
        ]);

        $importe = $this->importe($datos['importe']);
        $sub = isset($datos['subscription_id'])
            ? $customer->subscriptions()->findOrFail($datos['subscription_id'])
            : new Subscription(['customer_id' => $customer->id]);

        $sub->fill([
            'concepto' => $datos['concepto'], 'importe_centavos' => $importe,
            'desde' => $datos['desde'], 'hasta' => $datos['hasta'] ?? null, 'activa' => $importe > 0,
        ])->save();

        AuditLog::registrar('abono_guardado', $sub->only(['id', 'concepto', 'importe_centavos', 'desde', 'hasta']), $customer->id);

        return back()->with('ok', 'Abono guardado. Rige desde la próxima cuota generada.');
    }

    public function pagoManual(Request $request, Customer $customer, PaymentProcessor $processor): RedirectResponse
    {
        $datos = $request->validate([
            'metodo' => ['required', 'in:transferencia,efectivo,bonificacion'],
            'importe' => ['required', 'string', 'max:20'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'referencia' => ['nullable', 'string', 'max:90'],
            'nota' => ['nullable', 'string', 'max:200'],
        ]);

        $pago = $processor->registrarManual(
            $customer, $datos['metodo'], $this->importe($datos['importe']), Carbon::parse($datos['fecha']),
            $datos['referencia'] ?? null, $datos['nota'] ?? null, $request->user()?->id,
        );

        return $pago
            ? back()->with('ok', 'Pago registrado: '.Money::formato($pago->importe_centavos))
            : back()->with('error', 'Ese número de operación ya estaba registrado. No se cargó de nuevo.');
    }

    public function ajuste(Request $request, Customer $customer, PaymentProcessor $processor): RedirectResponse
    {
        $datos = $request->validate([
            'importe' => ['required', 'string', 'max:20'],
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
        ]);

        $importe = Money::centavos($datos['importe']);
        abort_if($importe === 0, 422, 'El ajuste no puede ser 0.');
        $processor->ajustar($customer, $importe, $datos['motivo'], $request->user()?->id);

        return back()->with('ok', 'Ajuste registrado.');
    }

    public function regenerarLink(Customer $customer): RedirectResponse
    {
        $customer->update(['pay_token' => Customer::nuevoToken()]);
        AuditLog::registrar('link_regenerado', [], $customer->id, 'warning');

        return back()->with('ok', 'Link de pago regenerado. El anterior dejó de funcionar.');
    }

    public function regenerarApiKey(Customer $customer): RedirectResponse
    {
        $key = $customer->regenerarApiKey();
        AuditLog::registrar('api_key_regenerada', [], $customer->id, 'warning');

        return back()->with('api_key', $key);
    }

    private function importe(string $valor): int
    {
        try {
            $c = Money::centavos($valor);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
        abort_if($c < 0, 422, 'El importe no puede ser negativo.');

        return $c;
    }
}
