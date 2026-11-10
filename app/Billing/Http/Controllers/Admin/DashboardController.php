<?php

namespace App\Billing\Http\Controllers\Admin;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Customer;
use App\Billing\Models\Invoice;
use App\Billing\Models\LedgerEntry;
use App\Billing\Models\LegacyDetalle;
use App\Billing\Models\Reconciliation;
use App\Billing\Models\Subscription;
use App\Billing\Models\WebhookEvent;
use App\Billing\Services\CustomerStatusService;
use App\Billing\Services\DunningService;
use App\Billing\Services\LedgerService;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(LedgerService $ledger, CustomerStatusService $status): View
    {
        $hoy = CustomerStatusService::hoy();
        $periodo = $hoy->format('Y-m');
        $saldos = $ledger->saldos();
        $tol = (int) config('billing.reglas.tolerancia_centavos');

        $niveles = [];
        $aging = ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'mas_90' => 0];
        $deudores = [];

        foreach (Customer::where('estado', 'activo')->get() as $c) {
            $saldo = $saldos[$c->id] ?? 0;
            $e = $status->estado($c, $hoy, $saldo);
            $niveles[$e['nivel']] = ($niveles[$e['nivel']] ?? 0) + 1;
            if ($saldo > $tol) {
                foreach ($ledger->aging($c, $hoy) as $k => $v) {
                    $aging[$k] += $v;
                }
                $deudores[] = ['c' => $c, 'saldo' => $saldo, 'nivel' => $e['nivel']];
            }
        }
        usort($deudores, fn ($a, $b) => $b['saldo'] <=> $a['saldo']);

        $facturado = (int) LedgerEntry::where('importe_centavos', '>', 0)
            ->whereIn('tipo', ['cuota', 'recargo', 'legacy_cargo'])
            ->whereBetween('fecha', [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()])->sum('importe_centavos');
        $cobrado = -1 * (int) LedgerEntry::whereIn('tipo', ['pago', 'legacy_pago'])
            ->whereBetween('fecha', [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()])->sum('importe_centavos');

        return view('billing.admin.dashboard', [
            'periodo' => $periodo,
            'mrr' => (int) Subscription::where('activa', true)
                ->whereHas('customer', fn ($q) => $q->where('estado', 'activo'))->sum('importe_centavos'),
            'facturado' => $facturado,
            'cobrado' => $cobrado,
            'tasa' => $facturado > 0 ? round($cobrado / $facturado * 100) : null,
            'deudaTotal' => array_sum(array_filter($saldos, fn ($s) => $s > $tol)),
            'aging' => $aging,
            'niveles' => $niveles,
            'deudores' => array_slice($deudores, 0, 15),
            'ultimosPagos' => LedgerEntry::with('customer')->whereIn('tipo', ['legacy_pago', 'pago', 'bonificacion'])->orderByDesc('fecha')->limit(10)->get(),
            'webhooksConError' => WebhookEvent::whereNull('procesado_at')->where('created_at', '<', now()->subMinutes(30))->count(),
            'ultimaConciliacion' => Reconciliation::latest('id')->first(),
            'alertas' => AuditLog::whereIn('nivel', ['warning', 'alerta', 'error'])->latest('id')->limit(10)->get(),
            'cuotasMes' => Invoice::where('periodo', $periodo)->count(),
        ]);
    }

    public function pagos(): View
    {
        $pagos = LedgerEntry::with('customer')
            ->whereIn('tipo', ['legacy_pago', 'pago', 'bonificacion'])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(50);

        return view('billing.admin.pagos', [
            'pagos' => $pagos,
            'detalles' => $this->detallesDe($pagos->getCollection()),
        ]);
    }

    /** @param \Illuminate\Support\Collection<int, LedgerEntry> $movimientos */
    private function detallesDe($movimientos)
    {
        $ids = $movimientos->map(fn (LedgerEntry $e) => LegacyDetalle::idDesdeReferencia($e->referencia))->filter()->all();

        return LegacyDetalle::whereIn('legacy_movimiento_id', $ids)->get()->keyBy('legacy_movimiento_id');
    }

    public function avisos(DunningService $dunning): View
    {
        return view('billing.admin.avisos', [
            'cola' => $dunning->cola(CustomerStatusService::hoy()),
            'automaticoActivo' => (bool) config('billing.avisos.enabled'),
        ]);
    }

    public function eventos(): View
    {
        return view('billing.admin.eventos', [
            'webhooks' => WebhookEvent::latest('id')->limit(100)->get(),
            'conciliaciones' => Reconciliation::latest('id')->limit(30)->get(),
            'auditoria' => AuditLog::latest('id')->limit(150)->get(),
        ]);
    }
}
