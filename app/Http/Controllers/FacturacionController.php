<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoVenta;
use App\Models\Venta;
use App\Services\Afip\FacturacionService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacturacionController extends Controller
{
    public function index(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $comprobantes = Comprobante::with(['venta', 'emisor', 'puntoVenta'])
            ->whereBetween('fecha_emision', [$desde->toDateString(), $hasta->toDateString()])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('facturacion.index', [
            'comprobantes' => $comprobantes,
            'desde' => $desde,
            'hasta' => $hasta,
            'hayEmisores' => Emisor::where('activo', true)->exists(),
        ]);
    }

    public function facturar(Request $request, Venta $venta, FacturacionService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('facturacion.emitir'), 403);

        $datos = $request->validate([
            'emisor_id' => ['required', 'exists:emisores,id'],
            'punto_venta_id' => ['required', 'exists:puntos_venta,id'],
        ]);

        $emisor = Emisor::findOrFail($datos['emisor_id']);
        $puntoVenta = PuntoVenta::where('emisor_id', $emisor->id)
            ->where('activo', true)
            ->findOrFail($datos['punto_venta_id']);

        $comprobante = $servicio->facturarVenta($venta, $emisor, $puntoVenta, auth()->id());

        return match ($comprobante->estado) {
            'autorizado' => redirect()->route('facturacion.show', $comprobante)
                ->with('ok', "Comprobante {$comprobante->numeroFormateado()} autorizado. CAE: {$comprobante->cae}"),
            default => back()->with('error', "AFIP no autorizó el comprobante: {$comprobante->mensaje_afip}"),
        };
    }

    public function reintentar(Comprobante $comprobante, FacturacionService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('facturacion.emitir'), 403);

        if ($comprobante->estado === 'autorizado') {
            return back()->with('error', 'El comprobante ya está autorizado.');
        }

        $comprobante->update(['estado' => 'pendiente', 'mensaje_afip' => null]);

        try {
            $resultado = app(\App\Services\Afip\WsfeService::class)->autorizar($comprobante);
        } catch (\Throwable $e) {
            $comprobante->update(['estado' => 'error', 'mensaje_afip' => $e->getMessage()]);

            return back()->with('error', "Error al reintentar: {$e->getMessage()}");
        }

        return $resultado->estado === 'autorizado'
            ? redirect()->route('facturacion.show', $resultado)
                ->with('ok', "Comprobante autorizado. CAE: {$resultado->cae}")
            : back()->with('error', "AFIP no autorizó: {$resultado->mensaje_afip}");
    }

    public function manualForm(): View
    {
        return view('facturacion.manual', [
            'emisores' => Emisor::with('puntosVenta')->where('activo', true)->get(),
            'clientes' => \App\Models\Cliente::where('activo', true)->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo_documento', 'documento', 'condicion_iva']),
        ]);
    }

    public function manualStore(Request $request, FacturacionService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('facturacion.emitir'), 403);

        $datos = $request->validate([
            'emisor_id' => ['required', 'exists:emisores,id'],
            'punto_venta_id' => ['required', 'exists:puntos_venta,id'],
            'receptor_nombre' => ['nullable', 'string', 'max:255'],
            'receptor_condicion_iva' => ['required', 'in:CONSUMIDOR_FINAL,RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'doc_tipo' => ['required', 'in:80,86,96,99'],
            'doc_numero' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'items.*.alicuota_iva' => ['required', 'numeric', 'in:0,10.5,21,27'],
        ]);

        $emisor = Emisor::findOrFail($datos['emisor_id']);
        $puntoVenta = PuntoVenta::where('emisor_id', $emisor->id)->findOrFail($datos['punto_venta_id']);

        $comprobante = $servicio->facturaManual($datos, $emisor, $puntoVenta, auth()->id());

        return match ($comprobante->estado) {
            'autorizado' => redirect()->route('facturacion.show', $comprobante)
                ->with('ok', "Comprobante {$comprobante->numeroFormateado()} autorizado. CAE: {$comprobante->cae}"),
            default => redirect()->route('facturacion.index')
                ->with('error', "AFIP no autorizó el comprobante: {$comprobante->mensaje_afip}"),
        };
    }

    public function notaForm(Comprobante $comprobante): View
    {
        abort_if($comprobante->estado !== 'autorizado', 404);

        return view('facturacion.nota', [
            'comprobante' => $comprobante->load(['emisor', 'puntoVenta']),
        ]);
    }

    public function notaStore(Request $request, Comprobante $comprobante, FacturacionService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('facturacion.emitir'), 403);

        $datos = $request->validate([
            'clase' => ['required', 'in:credito,debito'],
            'importe' => ['nullable', 'numeric', 'gt:0'],
            'concepto' => ['required', 'string', 'max:255'],
        ]);

        $nota = $servicio->nota(
            $comprobante,
            $datos['clase'],
            isset($datos['importe']) ? (float) $datos['importe'] : null,
            $datos['concepto'],
            auth()->id(),
        );

        return match ($nota->estado) {
            'autorizado' => redirect()->route('facturacion.show', $nota)
                ->with('ok', "{$nota->tipoNombre()} {$nota->numeroFormateado()} autorizada. CAE: {$nota->cae}"),
            default => redirect()->route('facturacion.index')
                ->with('error', "AFIP no autorizó la nota: {$nota->mensaje_afip}"),
        };
    }

    public function show(Comprobante $comprobante): View
    {
        $comprobante->load(['venta.items', 'emisor', 'puntoVenta']);

        return view('facturacion.factura', [
            'comprobante' => $comprobante,
            'qr' => $comprobante->estado === 'autorizado' ? $this->qrAfip($comprobante) : null,
        ]);
    }

    /**
     * QR oficial AFIP (RG 4892): URL con el JSON del comprobante en base64.
     */
    private function qrAfip(Comprobante $comprobante): string
    {
        $payload = base64_encode(json_encode([
            'ver' => 1,
            'fecha' => $comprobante->fecha_emision->format('Y-m-d'),
            'cuit' => (int) preg_replace('/\D/', '', $comprobante->emisor->cuit),
            'ptoVta' => $comprobante->puntoVenta->numero,
            'tipoCmp' => $comprobante->tipo_comprobante,
            'nroCmp' => (int) $comprobante->numero,
            'importe' => (float) $comprobante->total,
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => $comprobante->doc_tipo,
            'nroDocRec' => (int) $comprobante->doc_numero,
            'tipoCodAut' => 'E',
            'codAut' => (int) $comprobante->cae,
        ]));

        $url = "https://www.afip.gob.ar/fe/qr/?p={$payload}";

        return (new QRCode(new QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::L,
        ])))->render($url);
    }
}
