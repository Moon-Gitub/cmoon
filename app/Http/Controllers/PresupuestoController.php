<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Models\PresupuestoItem;
use App\Models\Producto;
use App\Services\PresupuestoService;
use App\Support\TableSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PresupuestoController extends Controller
{
    public function __construct(private PresupuestoService $presupuestos) {}

    public function index(Request $request): View
    {
        $query = Presupuesto::with(['cliente', 'usuario'])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado));

        [$sort, $dir] = TableSort::apply($query, $request, [
            'numero' => 'id',
            'fecha' => 'fecha',
            'cliente' => fn ($q, $d) => $q->orderBy(
                \App\Models\Cliente::select('nombre')->whereColumn('clientes.id', 'presupuestos.cliente_id'),
                $d
            ),
            'vendedor' => fn ($q, $d) => $q->orderBy(
                \App\Models\User::select('name')->whereColumn('users.id', 'presupuestos.user_id'),
                $d
            ),
            'total' => 'total',
            'estado' => 'estado',
        ], 'numero', 'desc');

        $presupuestos = $query->paginate(25)->withQueryString();

        $pendientesAprobacion = Presupuesto::where('estado', 'pendiente_aprobacion')->count();

        return view('presupuestos.index', compact('presupuestos', 'pendientesAprobacion', 'sort', 'dir'));
    }

    public function create(): View
    {
        return view('presupuestos.create', [
            'clientes' => \App\Models\Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos' => Producto::where('activo', true)->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_venta']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('presupuestos.gestionar'), 403);

        $datos = $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'valido_hasta' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $presupuesto = $this->presupuestos->crear(
            auth()->user()->empresa_id,
            auth()->id(),
            $datos['items'],
            $datos['cliente_id'] ?? null,
            $datos['observaciones'] ?? null,
            $datos['valido_hasta'] ?? null,
            'pendiente',
            'web',
        );

        return redirect()->route('presupuestos.show', $presupuesto)
            ->with('ok', "Presupuesto #{$presupuesto->numero} creado.");
    }

    public function show(Presupuesto $presupuesto): View
    {
        return view('presupuestos.show', [
            'presupuesto' => $presupuesto->load(['items.producto', 'cliente', 'usuario', 'venta']),
            'empresa' => auth()->user()->empresa,
        ]);
    }

    public function aprobar(Presupuesto $presupuesto): RedirectResponse
    {
        abort_unless(auth()->user()->can('presupuestos.aprobar'), 403);

        $this->presupuestos->aprobar($presupuesto);

        return back()->with('ok', "Pedido #{$presupuesto->numero} aprobado. Ya puede convertirse en venta.");
    }

    public function rechazar(Request $request, Presupuesto $presupuesto): RedirectResponse
    {
        abort_unless(auth()->user()->can('presupuestos.aprobar'), 403);

        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $this->presupuestos->rechazar($presupuesto, $datos['motivo'] ?? null);

        return back()->with('ok', "Pedido #{$presupuesto->numero} rechazado.");
    }

    public function anular(Presupuesto $presupuesto): RedirectResponse
    {
        abort_unless(auth()->user()->can('presupuestos.gestionar'), 403);

        if ($presupuesto->estado === 'convertido') {
            return back()->with('error', 'No se puede anular: ya fue convertido en venta.');
        }

        $presupuesto->update(['estado' => 'anulado']);

        return back()->with('ok', "Presupuesto #{$presupuesto->numero} anulado.");
    }
}
