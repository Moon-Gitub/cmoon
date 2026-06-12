<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MovimientoCuenta;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuentaCorrienteController extends Controller
{
    public function cliente(Cliente $cliente): View
    {
        return $this->vistaCuenta($cliente, $cliente->nombre, 'clientes.index');
    }

    public function proveedor(Proveedor $proveedor): View
    {
        return $this->vistaCuenta($proveedor, $proveedor->razon_social, 'proveedores.index');
    }

    public function registrarCliente(Request $request, Cliente $cliente): RedirectResponse
    {
        return $this->registrar($request, $cliente);
    }

    public function registrarProveedor(Request $request, Proveedor $proveedor): RedirectResponse
    {
        return $this->registrar($request, $proveedor);
    }

    private function vistaCuenta(Model $titular, string $nombre, string $rutaVolver): View
    {
        $movimientos = $titular->movimientosCuenta()
            ->with('usuario')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25);

        return view('cuentas.show', [
            'titular' => $titular,
            'nombre' => $nombre,
            'rutaVolver' => $rutaVolver,
            'movimientos' => $movimientos,
            'saldo' => $titular->saldoCuenta(),
            'esCliente' => $titular instanceof Cliente,
        ]);
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

        // factura/cargo suma deuda; pago la reduce; ajuste respeta el signo elegido
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
