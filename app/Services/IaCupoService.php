<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\IaCompra;
use App\Models\IaPaquete;
use App\Models\IaUsoMensual;
use Illuminate\Support\Facades\DB;

class IaCupoService
{
    /** @return array{ok: bool, usados: int, cupo: int, restantes: int, plan: string, extras: int, mensual: int, mensaje?: string} */
    public function resumen(int $empresaId): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $mensual = $empresa->cupoIaMensualBase();
        $extras = (int) $empresa->ia_creditos_extra;
        $usados = $this->usados($empresaId);
        $restantesMensual = max(0, $mensual - $usados);
        $restantes = $restantesMensual + $extras;
        $plan = $empresa->abonoIaVigente() ? 'abono' : 'incluido';

        return [
            'ok' => $restantes > 0,
            'usados' => $usados,
            'cupo' => $mensual + $extras,
            'mensual' => $mensual,
            'extras' => $extras,
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
            $mensual = $empresa->cupoIaMensualBase();

            if ($uso->usados < $mensual) {
                $uso->increment('usados');

                return true;
            }

            if ((int) $empresa->ia_creditos_extra > 0) {
                $empresa->decrement('ia_creditos_extra');
                $uso->increment('usados');

                return true;
            }

            return false;
        });
    }

    public function acreditar(int $empresaId, int $creditos): void
    {
        if ($creditos <= 0) {
            return;
        }

        Empresa::query()->where('id', $empresaId)->increment('ia_creditos_extra', $creditos);
    }

    public function solicitarPaquete(int $empresaId, int $userId, IaPaquete $paquete): IaCompra
    {
        return IaCompra::query()->create([
            'empresa_id' => $empresaId,
            'user_id' => $userId,
            'paquete_id' => $paquete->id,
            'creditos' => $paquete->creditos,
            'precio' => $paquete->precio,
            'estado' => 'solicitada',
        ]);
    }

    public function aprobarCompra(IaCompra $compra, int $aprobadorId): IaCompra
    {
        return DB::transaction(function () use ($compra, $aprobadorId) {
            $compra = IaCompra::query()->lockForUpdate()->findOrFail($compra->id);
            if ($compra->estado !== 'solicitada') {
                return $compra;
            }

            $this->acreditar((int) $compra->empresa_id, (int) $compra->creditos);
            $compra->update([
                'estado' => 'aprobada',
                'aprobado_por' => $aprobadorId,
                'aprobado_at' => now(),
            ]);

            return $compra->fresh();
        });
    }

    public function rechazarCompra(IaCompra $compra, int $aprobadorId, ?string $notas = null): IaCompra
    {
        $compra->update([
            'estado' => 'rechazada',
            'aprobado_por' => $aprobadorId,
            'aprobado_at' => now(),
            'notas' => $notas ?? $compra->notas,
        ]);

        return $compra->fresh();
    }

    public function mensajeLimite(int $empresaId): string
    {
        $r = $this->resumen($empresaId);

        return "Llegaste a las {$r['cupo']} consultas de IA disponibles "
            ."(plan {$r['plan']}: {$r['mensual']}/mes"
            .($r['extras'] > 0 ? " + {$r['extras']} créditos" : '')
            .'). Comprá más créditos desde el Asistente o pedile a soporte que te acredite.';
    }

    private function usados(int $empresaId): int
    {
        return (int) IaUsoMensual::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo', now()->format('Y-m'))
            ->value('usados');
    }
}
