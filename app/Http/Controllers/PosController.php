<?php

namespace App\Http\Controllers;

use App\Models\CajaSesion;
use App\Models\BalanzaFormato;
use App\Models\Cliente;
use App\Models\Emisor;
use App\Models\ListaPrecio;
use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\Afip\FacturacionService;
use App\Services\MercadoPagoQrService;
use App\Services\VentaService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = auth()->user();
        $sucursal = $usuario->sucursal ?? Sucursal::where('activa', true)->first();

        $sesionesAbiertas = CajaSesion::with('caja')
            ->where('user_id', $usuario->id)
            ->where('estado', 'abierta')
            ->orderByDesc('abierta_at')
            ->get();

        $sesionElegidaId = (int) ($request->session()->get('pos_caja_sesion_id') ?? 0);
        $sesionAbierta = $sesionesAbiertas->firstWhere('id', $sesionElegidaId)
            ?? $sesionesAbiertas->first();

        if ($sesionAbierta) {
            $request->session()->put('pos_caja_sesion_id', $sesionAbierta->id);
        }

        $presupuesto = null;
        $presupuestoItems = [];
        if ($request->filled('presupuesto')) {
            $presupuesto = \App\Models\Presupuesto::with('items.producto')
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->find($request->integer('presupuesto'));

            $presupuestoItems = $presupuesto?->items->map(fn ($i) => [
                'producto_id' => $i->producto_id,
                'codigo' => $i->producto?->codigo ?? '',
                'nombre' => $i->descripcion,
                'cantidad' => (float) $i->cantidad,
                'precio' => (float) $i->precio_unitario,
                'iva' => (float) ($i->producto?->alicuota_iva ?? 21),
            ])->all() ?? [];
        }

        return view('pos.index', [
            'sucursal' => $sucursal,
            'sesionAbierta' => $sesionAbierta,
            'sesionesAbiertas' => $sesionesAbiertas,
            'presupuesto' => $presupuesto,
            'presupuestoItems' => $presupuestoItems,
            'puedeFacturar' => auth()->user()->can('facturacion.emitir'),
            'puedeCrearCliente' => auth()->user()->can('clientes.crear'),
        ]);
    }

    public function seleccionarCaja(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'caja_sesion_id' => ['required', 'integer', 'exists:caja_sesiones,id'],
        ]);

        $sesion = CajaSesion::where('id', $datos['caja_sesion_id'])
            ->where('user_id', auth()->id())
            ->where('estado', 'abierta')
            ->firstOrFail();

        $request->session()->put('pos_caja_sesion_id', $sesion->id);

        return response()->json([
            'ok' => true,
            'caja_sesion_id' => $sesion->id,
            'caja' => $sesion->caja?->nombre,
        ]);
    }

    public function catalogo(): JsonResponse
    {
        $medios = MedioPago::where('activo', true)
            ->orderByRaw("CASE WHEN tipo = 'efectivo' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'recargo_porcentaje']);

        $emisores = auth()->user()->can('facturacion.emitir')
            ? Emisor::with(['puntosVenta' => fn ($q) => $q->where('activo', true)])
                ->where('activo', true)
                ->get(['id', 'razon_social', 'cuit', 'condicion_iva'])
            : collect();

        return response()->json([
            'productos' => Producto::where('activo', true)
                ->get(['id', 'codigo', 'nombre', 'precio_venta', 'precio_compra', 'alicuota_iva', 'unidad', 'pesable'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio_venta,
                    'precio_compra' => (float) $p->precio_compra,
                    'iva' => (float) $p->alicuota_iva,
                    'unidad' => $p->unidad,
                    'pesable' => $p->pesable,
                ]),
            'clientes' => Cliente::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'documento', 'tipo_documento', 'condicion_iva', 'lista_precio_id']),
            'listas' => ListaPrecio::where('activa', true)
                ->get(['id', 'nombre', 'porcentaje', 'base'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'nombre' => $l->nombre,
                    'porcentaje' => (float) $l->porcentaje,
                    'base' => $l->base ?? 'venta',
                ]),
            'medios' => $medios
                ->when(
                    ! app(MercadoPagoQrService::class)->configurado(),
                    fn ($col) => $col->reject(fn ($m) => $m->tipo === 'qr')
                )
                ->values()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'nombre' => $m->nombre,
                    'tipo' => $m->tipo,
                    'recargo' => (float) $m->recargo_porcentaje,
                ]),
            'balanzas_formatos' => BalanzaFormato::configParaVenta(auth()->user()?->empresa_id)->values(),
            'emisores' => $emisores->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => $e->razon_social,
                'cuit' => $e->cuit,
                'condicion_iva' => $e->condicion_iva,
                'tipos_comprobante' => app(FacturacionService::class)->tiposDisponibles($e),
                'puntos_venta' => $e->puntosVenta->map(fn ($pv) => [
                    'id' => $pv->id,
                    'numero' => $pv->numero,
                    'descripcion' => $pv->descripcion,
                ]),
            ]),
            'mercadopago_qr' => app(MercadoPagoQrService::class)->configurado(),
        ]);
    }

    public function crearCliente(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('clientes.crear'), 403);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'in:DNI,CUIT,CUIL,OTRO'],
            'documento' => ['nullable', 'string', 'max:20'],
            'condicion_iva' => ['required', 'in:CONSUMIDOR_FINAL,RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'lista_precio_id' => ['nullable', Rule::exists('listas_precio', 'id')],
        ], [], [
            'tipo_documento' => 'tipo de documento',
            'condicion_iva' => 'condición frente al IVA',
        ]);

        $cliente = Cliente::create([
            ...$datos,
            'empresa_id' => auth()->user()->empresa_id,
            'activo' => true,
        ]);

        return response()->json([
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'documento' => $cliente->documento,
            'lista_precio_id' => $cliente->lista_precio_id,
        ], 201);
    }

    public function guardar(Request $request, VentaService $ventaService): JsonResponse
    {
        $datos = $request->validate([
            'uuid' => ['required', 'uuid'],
            'presupuesto_id' => ['nullable', 'exists:presupuestos,id'],
            'sucursal_id' => ['required', 'exists:sucursales,id'],
            'caja_sesion_id' => ['nullable', 'exists:caja_sesiones,id'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'recargo' => ['nullable', 'numeric', 'min:0'],
            'origen' => ['nullable', 'in:pos,offline'],
            'fecha' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.alicuota_iva' => ['nullable', 'numeric'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.medio_pago_id' => ['required', 'exists:medios_pago,id'],
            'pagos.*.importe' => ['required', 'numeric', 'gt:0'],
        ]);

        if (! empty($datos['caja_sesion_id'])) {
            $sesionOk = CajaSesion::where('id', $datos['caja_sesion_id'])
                ->where('user_id', auth()->id())
                ->where('estado', 'abierta')
                ->exists();
            if (! $sesionOk) {
                return response()->json(['message' => 'La sesión de caja no es válida o está cerrada.'], 422);
            }
        }

        // Sin permiso de editar fecha: siempre now() del servidor (timezone app).
        if (empty($datos['fecha']) || ! auth()->user()->can('ventas.editar_fecha')) {
            unset($datos['fecha']);
        }

        $venta = $ventaService->crear($datos, auth()->id());

        if (! empty($datos['presupuesto_id'])) {
            \App\Models\Presupuesto::where('id', $datos['presupuesto_id'])
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->update(['estado' => 'convertido', 'venta_id' => $venta->id]);
        }

        return response()->json([
            'id' => $venta->id,
            'numero' => $venta->numero,
            'total' => (float) $venta->total,
            'cliente_id' => $venta->cliente_id,
            'ticket_url' => route('ventas.ticket', $venta),
        ], 201);
    }

    public function facturar(Request $request, Venta $venta, FacturacionService $servicio): JsonResponse
    {
        abort_unless(auth()->user()->can('facturacion.emitir'), 403);

        $datos = $request->validate([
            'emisor_id' => ['required', 'exists:emisores,id'],
            'punto_venta_id' => ['required', 'exists:puntos_venta,id'],
            'tipo_comprobante' => ['required', 'integer', 'in:1,6,11'],
            'receptor_nombre' => ['nullable', 'string', 'max:255'],
            'receptor_condicion_iva' => ['nullable', 'in:CONSUMIDOR_FINAL,RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'doc_tipo' => ['nullable', 'integer', 'in:80,86,96,99'],
            'doc_numero' => ['nullable', 'string', 'max:20'],
        ]);

        $emisor = Emisor::findOrFail($datos['emisor_id']);
        $puntoVenta = $emisor->puntosVenta()->where('activo', true)->findOrFail($datos['punto_venta_id']);

        $opciones = array_filter([
            'tipo_comprobante' => (int) $datos['tipo_comprobante'],
            'receptor_nombre' => $datos['receptor_nombre'] ?? null,
            'receptor_condicion_iva' => $datos['receptor_condicion_iva'] ?? null,
            'doc_tipo' => isset($datos['doc_tipo']) ? (int) $datos['doc_tipo'] : null,
            'doc_numero' => $datos['doc_numero'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $comprobante = $servicio->facturarVenta($venta, $emisor, $puntoVenta, auth()->id(), $opciones);

        $observaciones = $this->observacionesAfip($comprobante);

        return response()->json([
            'estado' => $comprobante->estado,
            'cae' => $comprobante->cae,
            'numero' => $comprobante->numeroFormateado(),
            'tipo' => $comprobante->tipoNombre(),
            'mensaje' => $comprobante->mensaje_afip ?: (count($observaciones) ? implode(' | ', $observaciones) : null),
            'observaciones' => $observaciones,
            'factura_url' => $comprobante->estado === 'autorizado'
                ? route('facturacion.show', $comprobante)
                : null,
            'ticket_url' => $comprobante->estado === 'autorizado'
                ? route('facturacion.ticket', $comprobante)
                : null,
        ], $comprobante->estado === 'autorizado' ? 200 : 422);
    }

    /** @return list<string> */
    private function observacionesAfip(\App\Models\Comprobante $comprobante): array
    {
        if ($comprobante->mensaje_afip) {
            return array_values(array_filter(array_map('trim', explode(' | ', $comprobante->mensaje_afip))));
        }

        $obs = data_get($comprobante->respuesta_afip, 'FeDetResp.FECAEDetResponse.Observaciones.Obs');
        if (! $obs) {
            return [];
        }

        $lista = isset($obs['Code']) ? [$obs] : (array) $obs;

        return collect($lista)
            ->map(fn ($o) => is_array($o) ? "({$o['Code']}) ".($o['Msg'] ?? '') : (string) $o)
            ->filter()
            ->values()
            ->all();
    }

    public function crearQrMercadoPago(Request $request, MercadoPagoQrService $mp): JsonResponse
    {
        $datos = $request->validate([
            'total' => ['required', 'numeric', 'gt:0'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        $orden = $mp->crearOrden(
            (float) $datos['total'],
            $datos['titulo'] ?? 'Venta POSMoon',
            $datos['referencia'] ?? null,
        );

        $qrSvg = null;
        if ($orden['qr_data'] !== '') {
            $qrSvg = (new QRCode(new QROptions([
                'outputInterface' => QRMarkupSVG::class,
                'eccLevel' => EccLevel::M,
            ])))->render($orden['qr_data']);
        }

        return response()->json([
            ...$orden,
            'qr_svg' => $qrSvg,
        ]);
    }

    public function consultarQrMercadoPago(Request $request, MercadoPagoQrService $mp): JsonResponse
    {
        $datos = $request->validate([
            'referencia' => ['required', 'string', 'max:100'],
        ]);

        return response()->json($mp->consultarPago($datos['referencia']));
    }
}
