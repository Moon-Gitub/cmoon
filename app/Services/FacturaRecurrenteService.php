<?php

namespace App\Services;

use App\Models\FacturaRecurrente;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class FacturaRecurrenteService
{
    /**
     * @param  array{
     *   empresa_id?: int,
     *   cliente_id: int,
     *   emisor_id?: int|null,
     *   dia_mes: int,
     *   concepto: string,
     *   monto: float|string,
     *   activa?: bool,
     *   proximo_vencimiento?: string|null
     * }  $datos
     */
    public function crear(array $datos): FacturaRecurrente
    {
        $diaMes = max(1, min(31, (int) $datos['dia_mes']));

        return FacturaRecurrente::create([
            'empresa_id' => $datos['empresa_id'] ?? auth()->user()->empresa_id,
            'cliente_id' => $datos['cliente_id'],
            'emisor_id' => $datos['emisor_id'] ?? null,
            'dia_mes' => $diaMes,
            'proximo_vencimiento' => $datos['proximo_vencimiento']
                ?? $this->siguienteFecha($diaMes)->toDateString(),
            'concepto' => $datos['concepto'],
            'monto' => round((float) $datos['monto'], 2),
            'activa' => $datos['activa'] ?? true,
        ]);
    }

    public function procesarVencidas(): int
    {
        $hoy = now()->toDateString();
        $procesadas = 0;

        $vencidas = FacturaRecurrente::with('cliente')
            ->where('activa', true)
            ->whereDate('proximo_vencimiento', '<=', $hoy)
            ->orderBy('id')
            ->get();

        foreach ($vencidas as $factura) {
            $email = trim((string) ($factura->cliente?->email ?? ''));

            if ($email !== '') {
                $concepto = e($factura->concepto);
                $monto = number_format((float) $factura->monto, 2, ',', '.');
                $cliente = e($factura->cliente?->nombre ?? 'Cliente');
                $venc = $factura->proximo_vencimiento?->format('d/m/Y') ?? $hoy;

                $html = <<<HTML
                <div style="font-family:sans-serif;max-width:560px">
                  <h2 style="margin:0 0 12px">Recordatorio de factura recurrente</h2>
                  <p>Hola <strong>{$cliente}</strong>,</p>
                  <p>Te recordamos el próximo vencimiento:</p>
                  <ul>
                    <li>Concepto: {$concepto}</li>
                    <li>Monto: \$ {$monto}</li>
                    <li>Vencimiento: {$venc}</li>
                  </ul>
                  <p style="color:#666;font-size:13px">Este es un aviso automático de POSMoon.</p>
                </div>
                HTML;

                Mail::html($html, function ($message) use ($email, $factura) {
                    $message->to($email)->subject('Recordatorio: '.$factura->concepto);
                });

                $factura->ultimo_envio_at = now();
            }

            $factura->proximo_vencimiento = $this->avanzarMes(
                $factura->proximo_vencimiento ?? now(),
                (int) $factura->dia_mes
            );
            $factura->save();
            $procesadas++;
        }

        return $procesadas;
    }

    public function siguienteFecha(int $diaMes, ?Carbon $desde = null): Carbon
    {
        $desde = ($desde ?? now())->copy()->startOfDay();
        $diaMes = max(1, min(31, $diaMes));
        $candidato = $desde->copy()->day(min($diaMes, $desde->daysInMonth));

        if ($candidato->lt($desde)) {
            $siguienteMes = $desde->copy()->startOfMonth()->addMonth();
            $candidato = $siguienteMes->day(min($diaMes, $siguienteMes->daysInMonth));
        }

        return $candidato;
    }

    public function avanzarMes(Carbon $desde, int $diaMes): Carbon
    {
        $diaMes = max(1, min(31, $diaMes));
        $siguiente = $desde->copy()->startOfMonth()->addMonth();
        $nuevo = $siguiente->day(min($diaMes, $siguiente->daysInMonth));

        // Si quedó en el pasado (atrasos de varios meses), seguir avanzando.
        while ($nuevo->lte(now()->startOfDay())) {
            $siguiente = $nuevo->copy()->startOfMonth()->addMonth();
            $nuevo = $siguiente->day(min($diaMes, $siguiente->daysInMonth));
        }

        return $nuevo;
    }
}
