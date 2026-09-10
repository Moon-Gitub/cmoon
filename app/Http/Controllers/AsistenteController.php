<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\IaCompra;
use App\Models\IaMensaje;
use App\Models\IaPaquete;
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
            'paquetes' => IaPaquete::query()->where('activo', true)->orderBy('orden')->get(),
            'compras' => IaCompra::query()
                ->where('empresa_id', $empresaId)
                ->with('paquete')
                ->latest()
                ->limit(10)
                ->get(),
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

    public function comprarPaquete(Request $request, IaCupoService $cupo): RedirectResponse
    {
        $datos = $request->validate([
            'paquete_id' => ['required', 'exists:ia_paquetes,id'],
        ]);

        $paquete = IaPaquete::query()
            ->where('id', $datos['paquete_id'])
            ->where('activo', true)
            ->firstOrFail();

        $cupo->solicitarPaquete(
            (int) auth()->user()->empresa_id,
            (int) auth()->id(),
            $paquete,
        );

        return back()->with('ok', "Solicitud enviada: {$paquete->nombre} ({$paquete->creditos} créditos). Te avisamos cuando esté acreditado.");
    }
}
