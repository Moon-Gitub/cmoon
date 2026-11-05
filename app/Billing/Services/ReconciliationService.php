<?php

namespace App\Billing\Services;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Checkout;
use App\Billing\Models\LedgerEntry;
use App\Billing\Models\Payment;
use App\Billing\Models\Reconciliation;
use App\Billing\Support\Money;
use Throwable;

class ReconciliationService
{
    public function __construct(
        private MercadoPagoClient $mp,
        private PaymentProcessor $processor,
    ) {}

    /**
     * Compara Mercado Pago contra el libro: acredita lo faltante, revierte lo
     * reembolsado y alerta lo que no cierra (nunca borra nada).
     */
    public function conciliar(int $dias = 3): Reconciliation
    {
        $hasta = now();
        $desde = now()->subDays(max(1, $dias));
        $alertas = [];
        $revisados = 0;
        $acreditados = 0;

        try {
            $pagos = $this->mp->pagosEntre($desde, $hasta);
        } catch (Throwable $e) {
            return $this->guardar($desde, $hasta, 'error', 0, 0, [['tipo' => 'error_api', 'detalle' => $e->getMessage()]]);
        }

        $vistos = [];
        foreach ($pagos as $p) {
            if (! Checkout::esReferenciaPropia($p['external_reference'] ?? null)) {
                continue;
            }
            $revisados++;
            $id = (string) $p['id'];
            $vistos[$id] = $p['status'] ?? null;

            $acreditado = LedgerEntry::where('referencia', "mp:{$id}")->exists();
            $reversado = LedgerEntry::where('referencia', "mp-reverso:{$id}")->exists();
            $estado = $p['status'] ?? '';

            $requiere = ($estado === 'approved' && ! $acreditado)
                || (in_array($estado, PaymentProcessor::REVERSIBLES, true) && $acreditado && ! $reversado);

            if (! $requiere) {
                continue;
            }

            try {
                $r = $this->processor->procesarMercadoPago($id, 'conciliacion');
            } catch (Throwable $e) {
                $alertas[] = ['tipo' => 'error_proceso', 'payment_id' => $id, 'detalle' => $e->getMessage()];

                continue;
            }

            if ($r['resultado'] === 'acreditado') {
                $acreditados++;
                $alertas[] = ['tipo' => 'acreditado_por_conciliacion', 'payment_id' => $id, 'importe' => Money::formato($r['importe'])];
            } elseif ($r['resultado'] === 'revertido') {
                $alertas[] = ['tipo' => 'reverso_aplicado', 'payment_id' => $id, 'importe' => Money::formato($r['importe'])];
            } elseif (! in_array($r['resultado'], ['duplicado_ignorado', 'reverso_duplicado'], true)) {
                $alertas[] = ['tipo' => $r['resultado'], 'payment_id' => $id];
            }
        }

        // Asientos MP del período cuyo pago no figura aprobado en Mercado Pago.
        LedgerEntry::query()
            ->where('referencia', 'like', 'mp:%')
            ->whereBetween('fecha', [$desde, $hasta])
            ->each(function (LedgerEntry $e) use (&$alertas, $vistos) {
                $id = substr($e->referencia, 3);
                $estado = $vistos[$id] ?? Payment::where('proveedor', 'mercadopago')->where('external_id', $id)->value('estado');
                $reversado = LedgerEntry::where('referencia', "mp-reverso:{$id}")->exists();
                if ($estado !== 'approved' && ! $reversado) {
                    $alertas[] = ['tipo' => 'acreditacion_sin_respaldo', 'payment_id' => $id, 'estado_mp' => $estado, 'customer_id' => $e->customer_id];
                }
            });

        $graves = array_filter($alertas, fn ($a) => ! in_array($a['tipo'], ['acreditado_por_conciliacion', 'reverso_aplicado'], true));
        $estado = $graves ? 'diferencias' : 'ok';

        return $this->guardar($desde, $hasta, $estado, $revisados, $acreditados, $alertas);
    }

    /** Barrido de checkouts abiertos: rescata pagos cuyo webhook no llegó. */
    public function sincronizarPendientes(int $horas = 72): int
    {
        $procesados = 0;

        Checkout::query()
            ->where('estado', 'abierto')
            ->whereNotNull('preference_id')
            ->where('created_at', '>=', now()->subHours($horas))
            ->each(function (Checkout $c) use (&$procesados) {
                try {
                    foreach ($this->mp->pagosPorReferencia($c->external_reference) as $p) {
                        $r = $this->processor->procesarMercadoPago((string) $p['id'], 'sincronizacion');
                        $procesados += $r['resultado'] === 'acreditado' ? 1 : 0;
                    }
                } catch (Throwable $e) {
                    AuditLog::registrar('sync_pendientes_error', ['checkout' => $c->id, 'error' => $e->getMessage()], $c->customer_id, 'warning');
                }
            });

        Checkout::where('estado', 'abierto')->where('expira_at', '<', now())->update(['estado' => 'vencido']);

        return $procesados;
    }

    private function guardar($desde, $hasta, string $estado, int $revisados, int $acreditados, array $alertas): Reconciliation
    {
        $rec = Reconciliation::create(compact('desde', 'hasta', 'estado', 'revisados', 'acreditados', 'alertas'));

        AuditLog::registrar('conciliacion_'.$estado, ['id' => $rec->id, 'revisados' => $revisados, 'acreditados' => $acreditados, 'alertas' => count($alertas)], null, $estado === 'ok' ? 'info' : 'alerta');

        if ($estado !== 'ok' || $acreditados > 0) {
            app(AdminNotifier::class)->enviar(
                'Conciliación Moon Cobros: '.strtoupper($estado),
                "Revisados {$revisados} · acreditados {$acreditados} · alertas ".count($alertas)."\n".json_encode($alertas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            );
        }

        return $rec;
    }
}
