<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Retencion;
use App\Services\RetencionService;
use App\Services\SircarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class RetencionController extends Controller
{
    public function __construct(
        private readonly RetencionService $retenciones,
    ) {}

    public function index(Request $request): View
    {
        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();
        $proveedorId = $request->integer('proveedor_id') ?: null;

        $query = Retencion::with(['proveedor', 'usuario'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

        if ($proveedorId) {
            $query->where('proveedor_id', $proveedorId);
        }

        $retenciones = $query->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $totalPeriodo = (clone $query)->where('anulada', false)->sum('monto');

        return view('retenciones.index', [
            'retenciones' => $retenciones,
            'totalPeriodo' => $totalPeriodo,
            'desde' => $desde,
            'hasta' => $hasta,
            'proveedorId' => $proveedorId,
            'empresa' => $empresa,
            'defaults' => $this->retenciones->defaultsEmpresa($empresa),
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')
                ->get(['id', 'razon_social', 'cuit', 'alicuota_retencion_iibb']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('retenciones.gestionar'), 403);

        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);

        $datos = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'factura_numero' => ['required', 'string', 'max:30'],
            'factura_neto' => ['required', 'numeric', 'gt:0'],
            'alicuota' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fecha' => ['required', 'date'],
            'regimen' => ['nullable', 'integer', 'min:1'],
            'jurisdiccion' => ['nullable', 'integer', 'min:1'],
        ], [], [
            'proveedor_id' => 'proveedor',
            'factura_numero' => 'número de factura',
            'factura_neto' => 'neto de la factura',
            'alicuota' => 'alícuota',
        ]);

        $calculo = $this->retenciones->calcular(
            (float) $datos['factura_neto'],
            (float) $datos['alicuota'],
        );

        Retencion::create([
            'empresa_id' => $empresa->id,
            'proveedor_id' => $datos['proveedor_id'],
            'numero_recibo' => $this->retenciones->reservarNumeroRecibo($empresa),
            'user_id' => auth()->id(),
            'factura_numero' => $datos['factura_numero'],
            'factura_neto' => $datos['factura_neto'],
            'alicuota' => $datos['alicuota'],
            'monto' => $calculo['monto'],
            'monto_neto_pagado' => $calculo['neto'],
            'fecha' => $datos['fecha'],
            'regimen' => (int) ($datos['regimen'] ?? $empresa->tipo_regimen_retencion_default),
            'jurisdiccion' => (int) ($datos['jurisdiccion'] ?? $empresa->codigo_jurisdiccion_iibb),
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
        [$retenciones, $desde, $hasta] = $this->consultaExport($request);

        $nombre = 'retenciones_'.$desde->format('Ymd').'_'.$hasta->format('Ymd').'.txt';

        return response($sircar->generarTxt($retenciones), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    public function exportarZip(Request $request, SircarService $sircar): Response
    {
        [$retenciones, $desde, $hasta] = $this->consultaExport($request);

        $nombreTxt = 'retenciones_'.$desde->format('Ymd').'_'.$hasta->format('Ymd').'.txt';
        $contenido = $sircar->generarTxt($retenciones);

        $tmp = tempnam(sys_get_temp_dir(), 'sircar_');
        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo crear el archivo ZIP.');
        }
        $zip->addFromString($nombreTxt, $contenido);
        $zip->close();

        return response()->download($zipPath, str_replace('.txt', '.zip', $nombreTxt))->deleteFileAfterSend(true);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Carbon\Carbon, 2: \Carbon\Carbon} */
    private function consultaExport(Request $request): array
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();
        $proveedorId = $request->integer('proveedor_id') ?: null;

        $query = Retencion::with('proveedor')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

        if ($proveedorId) {
            $query->where('proveedor_id', $proveedorId);
        }

        return [
            $query->orderBy('fecha')->orderBy('id')->get(),
            $desde,
            $hasta,
        ];
    }
}
