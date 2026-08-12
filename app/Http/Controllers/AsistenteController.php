<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\IaMensaje;
use App\Services\AsistenteIaService;
use App\Services\IaCupoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AsistenteController extends Controller
{
    public function index(IaCupoService $cupo): View
    {
        $empresaId = auth()->user()->empresa_id;

        return view('asistente.index', [
            'cupo' => $cupo->resumen($empresaId),
            'mensajes' => IaMensaje::query()
                ->where('empresa_id', $empresaId)
                ->where('origen', 'panel')
                ->latest()
                ->limit(40)
                ->get()
                ->reverse()
                ->values(),
            'precio' => config('ia.abono_precio'),
        ]);
    }

    public function preguntar(Request $request, AsistenteIaService $asistente): JsonResponse
    {
        $datos = $request->validate([
            'mensaje' => ['required', 'string', 'max:2000'],
        ]);

        $r = $asistente->preguntar(
            (int) auth()->user()->empresa_id,
            $datos['mensaje'],
            auth()->id(),
            'panel',
        );

        return response()->json($r, ! empty($r['limite']) ? 429 : 200);
    }

    public function solicitarAbono(): RedirectResponse
    {
        Empresa::query()->where('id', auth()->user()->empresa_id)->update([
            'ia_abono_solicitado_at' => now(),
        ]);

        return back()->with('ok', 'Pedimos el abono de consultas IA. Cuando esté activo vas a tener más preguntas por mes.');
    }
}
