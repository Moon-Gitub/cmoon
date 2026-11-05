<?php

namespace App\Billing\Services;

use App\Billing\Models\Customer;
use App\Billing\Models\LedgerEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

class LedgerService
{
    public function saldo(Customer|int $customer): int
    {
        $id = $customer instanceof Customer ? $customer->id : $customer;

        return (int) LedgerEntry::where('customer_id', $id)->sum('importe_centavos');
    }

    /** @return array<int,int> customer_id => saldo */
    public function saldos(): array
    {
        return LedgerEntry::query()
            ->selectRaw('customer_id, SUM(importe_centavos) as saldo')
            ->groupBy('customer_id')
            ->pluck('saldo', 'customer_id')
            ->map(fn ($s) => (int) $s)
            ->all();
    }

    public function existe(string $referencia): bool
    {
        return LedgerEntry::where('referencia', $referencia)->exists();
    }

    /**
     * Registra un asiento. Devuelve null si la referencia ya existía (idempotente).
     */
    public function registrar(
        Customer $customer,
        string $tipo,
        int $importeCentavos,
        string $referencia,
        string $descripcion,
        string $origen,
        ?CarbonInterface $fecha = null,
        ?int $paymentId = null,
        ?int $invoiceId = null,
        ?int $userId = null,
    ): ?LedgerEntry {
        if ($this->existe($referencia)) {
            return null;
        }

        try {
            return LedgerEntry::create([
                'customer_id' => $customer->id,
                'fecha' => $fecha ?? now(),
                'tipo' => $tipo,
                'descripcion' => mb_substr($descripcion, 0, 255),
                'importe_centavos' => $importeCentavos,
                'referencia' => $referencia,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'origen' => $origen,
                'user_id' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Cargos todavía impagos, imputando los pagos en orden de antigüedad (FIFO).
     *
     * @return list<array{fecha: string, descripcion: string, tipo: string, pendiente: int}>
     */
    public function cargosPendientes(Customer $customer): array
    {
        $movs = LedgerEntry::where('customer_id', $customer->id)->orderBy('fecha')->orderBy('id')->get();
        $credito = -1 * (int) $movs->where('importe_centavos', '<', 0)->sum('importe_centavos');

        $pendientes = [];
        foreach ($movs->where('importe_centavos', '>', 0) as $m) {
            $aplicado = min($credito, $m->importe_centavos);
            $credito -= $aplicado;
            $resto = $m->importe_centavos - $aplicado;
            if ($resto > 0) {
                $pendientes[] = [
                    'fecha' => $m->fecha->toDateString(),
                    'descripcion' => $m->descripcion,
                    'tipo' => $m->tipo,
                    'pendiente' => $resto,
                ];
            }
        }

        return $pendientes;
    }

    /**
     * Antigüedad de la deuda en días: [0-30, 31-60, 61-90, +90] en centavos.
     *
     * @return array{0_30: int, 31_60: int, 61_90: int, mas_90: int}
     */
    public function aging(Customer $customer, CarbonInterface $hoy): array
    {
        $b = ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'mas_90' => 0];
        foreach ($this->cargosPendientes($customer) as $c) {
            $dias = (int) Carbon::parse($c['fecha'])->diffInDays($hoy, false);
            $clave = match (true) {
                $dias <= 30 => '0_30',
                $dias <= 60 => '31_60',
                $dias <= 90 => '61_90',
                default => 'mas_90',
            };
            $b[$clave] += $c['pendiente'];
        }

        return $b;
    }
}
