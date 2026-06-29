<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\MedioPago;
use App\Models\MovimientoCuenta;
use App\Models\Proveedor;
use App\Services\ProveedorCuentaCorrienteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuentaCorrienteController extends Controller
{
    public function __construct(
        private readonly ProveedorCuentaCorrienteService $proveedorCuenta,
    ) {}

    public function cliente(Cliente $cliente): View
    {
        return $this->vistaCuenta($cliente, $cliente->nombre, 'clientes.index');
    }

    public function proveedor(Proveedor $proveedor): View
    {
        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);

        return $this->vistaCuenta($proveedor, $proveedor->razon_social, 'proveedores.index', [
            'empresa' => $empresa,
            'mediosPago' => MedioPago::where('activo', true)->orderBy('nombre')->get(),
            'agenteRetencion' => $empresa->agente_retencion_iibb,
            'alicuotaProveedor' => (float) $proveedor->alicuota_retencion_iibb,
        ]);
    }

    public function registrarCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        return $this->registrar($request, $cliente);
    }

    public function registrarProveedor(Request $request, Proveedor $proveedor): RedirectResponse
    {
        return $this->registrar($request, $proveedor);
    }

    public function facturaProveedor(Request $request, Proveedor $proveedor): RedirectResponse
    {
        abort_unless(auth()->user()->can('cuentas.registrar'), 403);

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'factura_numero' => ['required', 'string', 'max:30'],
            'concepto' => ['nullable', 'string', 'max:255'],
            'neto_previo' => ['required', 'numeric', 'min:0'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'neto' => ['nullable', 'numeric', 'min:0'],
            'iva' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $this->proveedorCuenta->registrarFactura($proveedor, auth()->user(), $datos);

        return back()->with('ok', 'Factura registrada en cuenta corriente.');
    }

    public function pagoProveedor(Request $request, Proveedor $proveedor): RedirectResponse
    {
        abort_unless(auth()->user()->can('cuentas.registrar'), 403);

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'concepto' => ['nullable', 'string', 'max:255'],
            'importe' => ['nullable', 'numeric', 'min:0.01'],
            'medio_pago_id' => ['nullable', 'exists:medios_pago,id'],
            'bonificacion' => ['nullable', 'boolean'],
            'aplicar_retencion' => ['nullable', 'boolean'],
            'factura_numero' => ['nullable', 'string', 'max:30'],
            'fecha_retencion' => ['nullable', 'date'],
            'monto_sujeto' => ['nullable', 'numeric', 'min:0'],
            'alicuota' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'monto_retencion' => ['nullable', 'numeric', 'min:0'],
            'monto_neto' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->proveedorCuenta->registrarPago($proveedor, auth()->user(), $datos);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Pago registrado.');
    }

    private function vistaCuenta(Model $titular, string $nombre, string $rutaVolver, array $extra = []): View
    {
        $movimientos = $titular->movimientosCuenta()
            ->with(['usuario', 'medioPago'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25);

        return view('cuentas.show', array_merge([
            'titular' => $titular,
            'nombre' => $nombre,
            'rutaVolver' => $rutaVolver,
            'movimientos' => $movimientos,
            'saldo' => $titular->saldoCuenta(),
            'esCliente' => $titular instanceof Cliente,
        ], $extra));
    }

    private function registrar(Request $request, Model $titular): RedirectResponse
    {
        abort_unless(auth()->user()->can('cuentas.registrar'), 403);

        $datos = $request->validate([
            'tipo' => ['required', 'in:factura,pago,ajuste'],
            'concepto' => ['required', 'string', 'max:255'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
        ]);

        $importe = (float) $datos['importe'];
        if ($datos['tipo'] === 'pago') {
            $importe = -$importe;
        } elseif ($datos['tipo'] === 'ajuste' && $request->boolean('resta')) {
            $importe = -$importe;
        }

        MovimientoCuenta::create([
            'titular_type' => $titular->getMorphClass(),
            'titular_id' => $titular->getKey(),
            'tipo' => $datos['tipo'],
            'concepto' => $datos['concepto'],
            'importe' => $importe,
            'user_id' => auth()->id(),
            'fecha' => $datos['fecha'],
        ]);

        return back()->with('ok', 'Movimiento registrado.');
    }
}
