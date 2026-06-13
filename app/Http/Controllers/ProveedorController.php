<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $proveedores = Proveedor::query()
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(fn ($q) => $q
                    ->where('razon_social', 'like', "%{$buscar}%")
                    ->orWhere('cuit', 'like', "%{$buscar}%"));
            })
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('proveedores.crear'), 403);

        return view('proveedores.form', ['proveedor' => new Proveedor]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('proveedores.crear'), 403);

        $proveedor = Proveedor::create([
            ...$this->validar($request),
            'empresa_id' => auth()->user()->empresa_id,
        ]);

        return redirect()->route('proveedores.index')
            ->with('ok', "Proveedor {$proveedor->razon_social} creado.");
    }

    public function edit(Proveedor $proveedor): View
    {
        abort_unless(auth()->user()->can('proveedores.editar'), 403);

        return view('proveedores.form', compact('proveedor'));
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        abort_unless(auth()->user()->can('proveedores.editar'), 403);

        $proveedor->update($this->validar($request));

        return redirect()->route('proveedores.index')
            ->with('ok', "Proveedor {$proveedor->razon_social} actualizado.");
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        abort_unless(auth()->user()->can('proveedores.eliminar'), 403);

        if (round($proveedor->saldoCuenta(), 2) != 0) {
            return back()->with('error', 'No se puede eliminar: tiene saldo en cuenta corriente.');
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('ok', "Proveedor {$proveedor->razon_social} eliminado.");
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['nullable', 'string', 'max:13'],
            'condicion_iva' => ['required', 'in:RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'alicuota_retencion_iibb' => ['nullable', 'numeric', 'between:0,100'],
            'observaciones' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ], [], [
            'razon_social' => 'razón social',
            'condicion_iva' => 'condición frente al IVA',
            'alicuota_retencion_iibb' => 'alícuota de retención IIBB',
        ]) + [
            'activo' => $request->boolean('activo'),
            'alicuota_retencion_iibb' => $request->input('alicuota_retencion_iibb') ?: 0,
        ];
    }
}
