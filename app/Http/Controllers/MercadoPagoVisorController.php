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
        $puedeListar = method_exists($mp, 'listarPagos') || method_exists($mp, 'buscarPagos');

        if ($puedeListar) {
            try {
                $pagosMp = method_exists($mp, 'listarPagos')
                    ? $mp->listarPagos()
                    : $mp->buscarPagos();
            } catch (\Throwable) {
                $pagosMp = null;
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
            'ventasQr' => $ventasQr,
            'puedeListar' => $puedeListar && $pagosMp !== null,
        ]);
    }
}
