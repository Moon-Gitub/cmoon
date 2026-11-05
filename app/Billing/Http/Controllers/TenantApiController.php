<?php

namespace App\Billing\Http\Controllers;

use App\Billing\Models\Customer;
use App\Billing\Services\CustomerStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TenantApiController extends Controller
{
    public function estado(Request $request, CustomerStatusService $status): JsonResponse
    {
        $c = Customer::porApiKey((string) $request->bearerToken());
        if (! $c) {
            return response()->json(['message' => 'API key inválida.'], 401);
        }

        $e = $status->estado($c);

        return response()->json([
            'cliente' => $c->nombre,
            'nivel' => $e['nivel'],
            'bloquear' => $e['bloquear'],
            'saldo' => round(($e['saldo'] + $e['recargo']) / 100, 2),
            'mensaje' => $e['mensaje'],
            'dias_para_suspension' => $e['dias_para_suspension'],
            'pay_url' => $e['pay_url'],
            'consultado_at' => now()->toIso8601String(),
        ]);
    }
}
