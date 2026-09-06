<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CrmActividad;
use App\Models\CrmOportunidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmController extends Controller
{
    public const ETAPAS = [
        'nuevo' => 'Nuevo',
        'contacto' => 'Contacto',
        'propuesta' => 'Propuesta',
        'negociacion' => 'Negociación',
        'ganado' => 'Ganado',
        'perdido' => 'Perdido',
    ];

    public function index(): View
    {
        $oportunidades = CrmOportunidad::with(['cliente', 'usuario'])
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('etapa');

        $porEtapa = collect(self::ETAPAS)->mapWithKeys(
            fn ($label, $key) => [$key => $oportunidades->get($key, collect())]
        );

        return view('crm.index', [
            'porEtapa' => $porEtapa,
            'etapas' => self::ETAPAS,
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->limit(500)->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'etapa' => ['nullable', 'in:'.implode(',', array_keys(self::ETAPAS))],
            'monto' => ['nullable', 'numeric', 'min:0'],
            'probabilidad' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cierra_el' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
        ]);

        CrmOportunidad::create([
            'empresa_id' => auth()->user()->empresa_id,
            'user_id' => auth()->id(),
            'titulo' => $datos['titulo'],
            'cliente_id' => $datos['cliente_id'] ?? null,
            'etapa' => $datos['etapa'] ?? 'nuevo',
            'monto' => $datos['monto'] ?? null,
            'probabilidad' => $datos['probabilidad'] ?? 0,
            'cierra_el' => $datos['cierra_el'] ?? null,
            'notas' => $datos['notas'] ?? null,
        ]);

        return back()->with('ok', 'Oportunidad creada.');
    }

    public function updateEtapa(Request $request, CrmOportunidad $oportunidad): RedirectResponse
    {
        $datos = $request->validate([
            'etapa' => ['required', 'in:'.implode(',', array_keys(self::ETAPAS))],
        ]);

        $oportunidad->update(['etapa' => $datos['etapa']]);

        return back()->with('ok', 'Etapa actualizada.');
    }

    public function addActividad(Request $request, CrmOportunidad $oportunidad): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['nullable', 'string', 'max:30'],
            'titulo' => ['required', 'string', 'max:255'],
            'cuerpo' => ['nullable', 'string'],
        ]);

        CrmActividad::create([
            'oportunidad_id' => $oportunidad->id,
            'user_id' => auth()->id(),
            'tipo' => $datos['tipo'] ?? 'nota',
            'titulo' => $datos['titulo'],
            'cuerpo' => $datos['cuerpo'] ?? null,
            'hecha_at' => now(),
        ]);

        return back()->with('ok', 'Actividad registrada.');
    }
}
