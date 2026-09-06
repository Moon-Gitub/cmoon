<?php

namespace App\Http\Controllers;

use App\Services\CobranzaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CobranzaController extends Controller
{
    public function index(Request $request, CobranzaService $cobranza): View
    {
        $hasta = $request->date('hasta') ?? now()->addDays(30);

        return view('cobranzas.index', [
            'agenda' => $cobranza->agenda($hasta),
            'hasta' => $hasta,
        ]);
    }
}
