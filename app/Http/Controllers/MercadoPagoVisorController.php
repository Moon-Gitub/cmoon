<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\MercadoPagoQrService;
use Illuminate\View\View;

class MercadoPagoVisorController extends Controller
{
    public function index(MercadoPagoQrService $mp): View
    {
        $pagosMp = null;
        $liquidaciones = null;
        $liquidacionesFuente = null;
        $errorMp = null;

        if ($mp->configurado()) {
            try {
                $pagosMp = $mp->listarPagos();
            } catch (\Throwable $e) {
                $pagosMp = null;
                $errorMp = $e->getMessage();
            }

            try {
                $liq = $mp->listarLiquidaciones();
                $liquidaciones = $liq['items'] ?? [];
                $liquidacionesFuente = $liq['fuente'] ?? null;
            } catch (\Throwable $e) {
                $liquidaciones = null;
                $errorMp = $errorMp ?: $e->getMessage();
            }
        }

        $ventasQr = Venta::with(['cliente', 'pagos.medioPago'])
            ->where('estado', 'completada')
            ->whereHas('pagos.medioPago', fn ($q) => $q->where('tipo', 'qr'))
            ->orderByDesc('fecha')
            ->limit(40)
            ->get();

        return view('mercadopago.visor', [
            'configurado' => $mp->configurado(),
            'pagosMp' => $pagosMp,
            'liquidaciones' => $liquidaciones,
            'liquidacionesFuente' => $liquidacionesFuente,
            'ventasQr' => $ventasQr,
            'errorMp' => $errorMp,
        ]);
    }
}
