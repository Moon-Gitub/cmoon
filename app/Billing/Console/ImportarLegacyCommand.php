<?php

namespace App\Billing\Console;

use App\Billing\Services\LegacyImporter;
use App\Billing\Support\Money;
use Illuminate\Console\Command;

class ImportarLegacyCommand extends Command
{
    protected $signature = 'billing:importar-legacy {--dry-run : solo cuenta lo que importaría}';

    protected $description = 'Importa clientes y cuenta corriente desde cobrosposmooncom_db (solo lectura, re-ejecutable).';

    public function handle(LegacyImporter $importer): int
    {
        $r = $importer->importar((bool) $this->option('dry-run'));

        $this->table(['Clientes nuevos', 'Actualizados', 'Movimientos nuevos', 'Compensaciones'], [[
            $r['clientes_nuevos'], $r['clientes_actualizados'], $r['movimientos'], $r['diferencias'],
        ]]);

        if ($r['saldos_distintos']) {
            $this->error(count($r['saldos_distintos']).' clientes con saldo distinto al legacy:');
            $this->table(['Legacy ID', 'Cliente', 'Legacy', 'Hub'], array_map(fn ($d) => [
                $d['legacy_id'], $d['cliente'], Money::formato($d['legacy']), Money::formato($d['hub']),
            ], $r['saldos_distintos']));

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            $this->info('Saldos verificados: coinciden 1 a 1 con el legacy.');
        }

        return self::SUCCESS;
    }
}
