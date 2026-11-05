<?php

namespace App\Billing\Services;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Invoice;
use App\Billing\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceGenerator
{
    public function __construct(private LedgerService $ledger) {}

    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * Genera las cuotas del mes. Idempotente: UNIQUE(subscription_id, periodo)
     * y asiento con referencia "cuota:{invoice_id}".
     *
     * @return array{creadas: int, existentes: int}
     */
    public function generar(CarbonInterface $mes): array
    {
        $periodo = $mes->format('Y-m');
        $vto = $mes->copy()->startOfMonth()->day((int) config('billing.reglas.dia_vencimiento', 10));
        $etiqueta = self::MESES[(int) $mes->month].'/'.$mes->year;
        $creadas = 0;
        $existentes = 0;

        Subscription::query()
            ->with('customer')
            ->where('activa', true)
            ->where('importe_centavos', '>', 0)
            ->whereHas('customer', fn ($q) => $q->where('estado', 'activo'))
            ->orderBy('id')
            ->each(function (Subscription $s) use ($mes, $periodo, $vto, $etiqueta, &$creadas, &$existentes) {
                if (! $s->vigenteEn($mes)) {
                    return;
                }

                DB::transaction(function () use ($s, $periodo, $vto, $etiqueta, &$creadas, &$existentes) {
                    $invoice = Invoice::firstOrCreate(
                        ['subscription_id' => $s->id, 'periodo' => $periodo],
                        [
                            'customer_id' => $s->customer_id,
                            'concepto' => "{$s->concepto} {$etiqueta}",
                            'importe_centavos' => $s->importe_centavos,
                            'vencimiento' => $vto,
                        ],
                    );

                    $asiento = $this->ledger->registrar(
                        $s->customer,
                        'cuota',
                        $invoice->importe_centavos,
                        "cuota:{$invoice->id}",
                        $invoice->concepto,
                        'sistema',
                        $invoice->created_at,
                        invoiceId: $invoice->id,
                    );

                    $invoice->wasRecentlyCreated || $asiento ? $creadas++ : $existentes++;
                });
            });

        AuditLog::registrar('cuotas_generadas', compact('periodo', 'creadas', 'existentes'));

        return compact('creadas', 'existentes');
    }
}
