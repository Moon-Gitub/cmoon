<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Retencion;
use App\Services\SircarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RetencionController extends Controller
{
    public function index(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $retenciones = Retencion::with(['proveedor', 'usuario'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $totalPeriodo = Retencion::where('anulada', false)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->sum('monto');

        return view('retenciones.index', [
            'retenciones' => $retenciones,
            'totalPeriodo' => $totalPeriodo,
            'desde' => $desde,
            'hasta' => $hasta,
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')
                ->get(['id', 'razon_social', 'cuit', 'alicuota_retencion_iibb']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('retenciones.gestionar'), 403);

        $datos = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'factura_numero' => ['required', 'string', 'max:30'],
            'factura_neto' => ['required', 'numeric', 'gt:0'],
            'alicuota' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fecha' => ['required', 'date'],
            'regimen' => ['required', 'integer', 'min:1'],
            'jurisdiccion' => ['required', 'integer', 'min:1'],
        ], [], [
            'proveedor_id' => 'proveedor',
            'factura_numero' => 'número de factura',
            'factura_neto' => 'neto de la factura',
            'alicuota' => 'alícuota',
        ]);

        Retencion::create([
            ...$datos,
            'empresa_id' => auth()->user()->empresa_id,
            'user_id' => auth()->id(),
            'monto' => round((float) $datos['factura_neto'] * (float) $datos['alicuota'] / 100, 2),
        ]);

        return back()->with('ok', 'Retención registrada.');
    }

    public function anular(Retencion $retencion): RedirectResponse
    {
        abort_unless(auth()->user()->can('retenciones.gestionar'), 403);

        $retencion->update(['anulada' => ! $retencion->anulada]);

        return back()->with('ok', $retencion->anulada ? 'Retención anulada.' : 'Retención restaurada.');
    }

    public function exportarTxt(Request $request, SircarService $sircar): Response
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $retenciones = Retencion::with('proveedor')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')->orderBy('id')
            ->get();

        $nombre = 'sircar-retenciones-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.txt';

        return response($sircar->generarTxt($retenciones), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }
}
