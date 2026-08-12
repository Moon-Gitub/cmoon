<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\IaUsoMensual;
use Illuminate\Support\Facades\DB;

class IaCupoService
{
    /** @return array{ok: bool, usados: int, cupo: int, restantes: int, plan: string, mensaje?: string} */
    public function resumen(int $empresaId): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $cupo = $empresa->cupoIaMensual();
        $usados = $this->usados($empresaId);
        $restantes = max(0, $cupo - $usados);
        $plan = $empresa->abonoIaVigente() ? 'abono' : 'incluido';

        return [
            'ok' => $restantes > 0,
            'usados' => $usados,
            'cupo' => $cupo,
            'restantes' => $restantes,
            'plan' => $plan,
            'abono_hasta' => $empresa->ia_abono_hasta?->format('d/m/Y'),
            'solicitado' => (bool) $empresa->ia_abono_solicitado_at,
        ];
    }

    public function puedeConsumir(int $empresaId): bool
    {
        return $this->resumen($empresaId)['ok'];
    }

    public function consumir(int $empresaId): bool
    {
        return DB::transaction(function () use ($empresaId) {
            $empresa = Empresa::query()->lockForUpdate()->findOrFail($empresaId);
            $periodo = now()->format('Y-m');
            $uso = IaUsoMensual::query()->firstOrCreate(
                ['empresa_id' => $empresaId, 'periodo' => $periodo],
                ['usados' => 0]
            );
            $uso = IaUsoMensual::query()->where('id', $uso->id)->lockForUpdate()->first();
            $cupo = $empresa->cupoIaMensual();

            if ($uso->usados >= $cupo) {
                return false;
            }

            $uso->increment('usados');

            return true;
        });
    }

    public function mensajeLimite(int $empresaId): string
    {
        $r = $this->resumen($empresaId);
        $precio = config('ia.abono_precio');

        return "Llegaste a las {$r['cupo']} preguntas de IA de este mes (plan {$r['plan']}). "
            .'Para seguir, activá el abono mensual'
            .($precio && $precio !== 'consultar' ? " ({$precio})" : '')
            .'. Pedilo desde el Asistente o avisale a soporte.';
    }

    private function usados(int $empresaId): int
    {
        return (int) IaUsoMensual::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo', now()->format('Y-m'))
            ->value('usados');
    }
}
