<?php

namespace App\Billing\Services;

use App\Billing\Models\AuditLog;
use App\Billing\Models\Customer;
use App\Billing\Models\LedgerEntry;
use App\Billing\Models\LegacyDetalle;
use App\Billing\Models\Subscription;
use App\Billing\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importa clientes y cuenta corriente desde cobrosposmooncom_db (solo lectura).
 * Re-ejecutable: cada movimiento legacy queda como "legacy:{id}" y las ediciones
 * o borrados posteriores en el legacy se compensan con "legacy-diff:{id}:{n}".
 */
class LegacyImporter
{
    public function __construct(private LedgerService $ledger) {}

    /** @return array{clientes_nuevos: int, clientes_actualizados: int, movimientos: int, diferencias: int, saldos_distintos: list<array>} */
    public function importar(bool $dryRun = false): array
    {
        $legacy = DB::connection(config('billing.legacy.connection'));
        $excluir = array_map('intval', array_filter(explode(',', (string) config('billing.legacy.excluir'))));

        $clientes = $legacy->table('clientes')->whereNotIn('id', $excluir)->orderBy('id')->get();
        $movimientos = $legacy->table('clientes_cuenta_corriente')
            ->whereNotIn('id_cliente', $excluir)
            ->orderBy('id')
            ->get($this->columnas($legacy, 'clientes_cuenta_corriente', [
                'id', 'fecha', 'id_cliente', 'tipo', 'descripcion', 'importe', 'metodo_pago',
                'numero_recibo', 'referencia_mp', 'origen',
            ]));

        $r = ['clientes_nuevos' => 0, 'clientes_actualizados' => 0, 'movimientos' => 0, 'diferencias' => 0, 'saldos_distintos' => []];

        if ($dryRun) {
            $r['clientes_nuevos'] = $clientes->count();
            $r['movimientos'] = $movimientos->filter(fn ($m) => $this->importeFirmado($m) !== 0)->count();

            return $r;
        }

        $mapa = [];
        foreach ($clientes as $l) {
            $c = $this->upsertCliente($l, $r);
            $mapa[(int) $l->id] = $c;
        }

        $actual = $this->valoresActuales();
        $vigentes = [];

        foreach ($movimientos as $m) {
            $c = $mapa[(int) $m->id_cliente] ?? null;
            if (! $c) {
                continue;
            }
            $objetivo = $this->importeFirmado($m);
            $vigentes[(int) $m->id] = true;

            if (! isset($actual[(int) $m->id])) {
                if ($objetivo === 0) {
                    continue;
                }
                $this->ledger->registrar(
                    $c,
                    (int) $m->tipo === 1 ? 'legacy_pago' : 'legacy_cargo',
                    $objetivo,
                    "legacy:{$m->id}",
                    $this->descripcion($m),
                    'legacy',
                    $m->fecha ? Carbon::parse($m->fecha) : now(),
                ) && $r['movimientos']++;
                $this->guardarDetalle($c, $m);

                continue;
            }

            $this->guardarDetalle($c, $m);

            if ($actual[(int) $m->id]['importe'] !== $objetivo) {
                $this->compensar($actual[(int) $m->id], $objetivo, "editado en legacy: {$this->descripcion($m)}");
                $r['diferencias']++;
            }
        }

        foreach ($actual as $legacyId => $info) {
            if (! isset($vigentes[$legacyId]) && $info['importe'] !== 0) {
                $this->compensar($info, 0, 'movimiento borrado en legacy');
                $r['diferencias']++;
            }
        }

        $r['saldos_distintos'] = $this->verificarSaldos($legacy, $mapa);

        AuditLog::registrar('import_legacy', array_diff_key($r, ['saldos_distintos' => 1]) + ['saldos_distintos' => count($r['saldos_distintos'])], null, $r['saldos_distintos'] ? 'alerta' : 'info');

        return $r;
    }

    private function upsertCliente(object $l, array &$r): Customer
    {
        $email = filter_var(trim((string) ($l->email ?? '')), FILTER_VALIDATE_EMAIL) ?: null;
        $docRaw = trim((string) ($l->documento ?? ''));
        $doc = preg_replace('/\D/', '', $docRaw);
        $datos = [
            'nombre' => trim((string) $l->nombre),
            'email' => $email,
            'telefono' => trim((string) ($l->telefono ?? '')) ?: null,
            'direccion' => trim((string) ($l->direccion ?? '')) ?: null,
            'dominio' => trim((string) ($l->dominio ?? '')) ?: null,
            'tipo_documento' => trim((string) ($l->tipo_documento ?? '')) ?: null,
            'documento' => $docRaw !== '' ? $docRaw : null,
            'cuit' => strlen($doc) === 11 ? $doc : null,
            'condicion_iva' => trim((string) ($l->condicion_iva ?? '')) ?: null,
            'aplicar_recargos' => (bool) ($l->aplicar_recargos ?? true),
            'legacy_bloqueado' => (bool) ($l->estado_bloqueo ?? false),
            'notas' => trim((string) ($l->observaciones ?? '')) ?: null,
        ];

        $c = Customer::where('legacy_id', $l->id)->first();
        if (! $c) {
            $c = Customer::create($datos + ['legacy_id' => $l->id, 'plataforma' => 'legacy', 'estado' => 'activo', 'modo_cobro' => 'solo_aviso']);
            $r['clientes_nuevos']++;
        } else {
            $patch = $datos;
            foreach (['email', 'telefono', 'direccion', 'dominio', 'tipo_documento', 'documento', 'cuit', 'condicion_iva'] as $k) {
                if ($patch[$k] === null) {
                    unset($patch[$k]);
                }
            }
            if ($c->notas) {
                unset($patch['notas']);
            } elseif ($patch['notas'] === null) {
                unset($patch['notas']);
            }
            $c->fill($patch);
            if ($c->isDirty()) {
                $c->save();
                $r['clientes_actualizados']++;
            }
        }

        $mensual = (int) $l->mensual * 100;
        $abono = $c->subscriptions()->where('concepto', 'Abono mensual')->first();
        if (! $abono && $mensual > 0) {
            Subscription::create([
                'customer_id' => $c->id, 'concepto' => 'Abono mensual', 'importe_centavos' => $mensual,
                'desde' => now(config('billing.timezone'))->startOfMonth()->toDateString(), 'activa' => true,
            ]);
        } elseif ($abono && $abono->importe_centavos !== $mensual) {
            $abono->update(['importe_centavos' => $mensual, 'activa' => $mensual > 0]);
        }

        return $c;
    }

    /** @return array<int, array{importe: int, customer_id: int, diffs: int}> */
    private function valoresActuales(): array
    {
        $actual = [];
        LedgerEntry::where('origen', 'legacy')->get(['customer_id', 'referencia', 'importe_centavos'])
            ->each(function (LedgerEntry $e) use (&$actual) {
                if (! preg_match('/^legacy(?:-diff)?:(\d+)/', $e->referencia, $m)) {
                    return;
                }
                $id = (int) $m[1];
                $actual[$id] ??= ['importe' => 0, 'customer_id' => $e->customer_id, 'diffs' => 0, 'id' => $id];
                $actual[$id]['importe'] += $e->importe_centavos;
                if (str_starts_with($e->referencia, 'legacy-diff:')) {
                    $actual[$id]['diffs']++;
                }
            });

        return $actual;
    }

    private function compensar(array $info, int $objetivo, string $motivo): void
    {
        $c = Customer::find($info['customer_id']);
        $n = $info['diffs'] + 1;
        $this->ledger->registrar(
            $c, 'ajuste', $objetivo - $info['importe'], "legacy-diff:{$info['id']}:{$n}",
            "Ajuste legacy #{$info['id']} ({$motivo})", 'legacy', now(),
        );
    }

    /** @param list<string> $queridas */
    private function columnas($legacy, string $tabla, array $queridas): array
    {
        $schema = $legacy->getSchemaBuilder();

        return array_values(array_filter($queridas, fn (string $c) => $schema->hasColumn($tabla, $c)));
    }

    private function guardarDetalle(Customer $c, object $m): void
    {
        $datos = [
            'customer_id' => $c->id,
            'metodo_pago' => $this->metodo($m) ?: null,
            'numero_recibo' => trim((string) ($m->numero_recibo ?? '')) ?: null,
            'referencia_mp' => trim((string) ($m->referencia_mp ?? '')) ?: null,
            'origen' => trim((string) ($m->origen ?? '')) ?: null,
        ];
        if (! array_filter($datos, fn ($v, $k) => $k !== 'customer_id' && $v !== null, ARRAY_FILTER_USE_BOTH)) {
            return;
        }

        LegacyDetalle::updateOrCreate(['legacy_movimiento_id' => (int) $m->id], $datos);
    }

    private function metodo(object $m): string
    {
        $metodo = trim((string) ($m->metodo_pago ?? ''));
        if ($metodo !== '' && str_starts_with($metodo, '[')) {
            $metodo = (string) (json_decode($metodo, true)[0]['tipo'] ?? '');
        }

        return $metodo;
    }

    private function importeFirmado(object $m): int
    {
        $centavos = Money::centavos((float) $m->importe);

        return (int) $m->tipo === 1 ? -$centavos : $centavos;
    }

    private function descripcion(object $m): string
    {
        $extra = array_filter([
            $this->metodo($m),
            ($recibo = trim((string) ($m->numero_recibo ?? ''))) !== '' ? "recibo {$recibo}" : null,
            ($ref = trim((string) ($m->referencia_mp ?? ''))) !== '' ? $ref : null,
        ]);

        return trim(((string) $m->descripcion ?: 'Movimiento legacy').($extra ? ' · '.implode(' · ', $extra) : ''));
    }

    /** @param array<int, Customer> $mapa */
    private function verificarSaldos($legacy, array $mapa): array
    {
        $saldosLegacy = $legacy->table('clientes_cuenta_corriente')
            ->selectRaw('id_cliente, SUM(CASE WHEN tipo = 1 THEN -importe ELSE importe END) as saldo')
            ->groupBy('id_cliente')
            ->pluck('saldo', 'id_cliente');

        $distintos = [];
        foreach ($mapa as $legacyId => $c) {
            $esperado = Money::centavos((float) ($saldosLegacy[$legacyId] ?? 0));
            $hub = (int) LedgerEntry::where('customer_id', $c->id)->where('origen', 'legacy')->sum('importe_centavos');
            if ($esperado !== $hub) {
                $distintos[] = ['legacy_id' => $legacyId, 'cliente' => $c->nombre, 'legacy' => $esperado, 'hub' => $hub];
            }
        }

        return $distintos;
    }
}
