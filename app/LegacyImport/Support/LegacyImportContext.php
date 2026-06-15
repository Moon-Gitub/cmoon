<?php

namespace App\LegacyImport\Support;

use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

class LegacyImportContext
{
    /** @var array<string, int> clave stkProd legacy → sucursal_id POSMoon */
    public array $sucursalMap = [];

    public ?int $defaultSucursalId = null;

    public ?int $defaultUserId = null;

    public ?int $defaultEmisorId = null;

    /** @var array<int, int> número punto venta → punto_venta_id */
    public array $puntoVentaMap = [];

    public function __construct(
        public readonly int $empresaId,
        public readonly int $legacyEmpresaId,
        public readonly IdMap $idMap,
        public readonly ImportStats $stats,
        public readonly bool $dryRun = false,
        public readonly bool $force = false,
    ) {}

    public function legacy(string $table)
    {
        return DB::connection(config('legacy.connection'))->table($table);
    }

    public function empresa(): Empresa
    {
        return Empresa::findOrFail($this->empresaId);
    }

    public function resolveSucursal(?string $legacyKey): ?int
    {
        if ($legacyKey === null || $legacyKey === '') {
            return $this->defaultSucursalId;
        }

        return $this->sucursalMap[$legacyKey] ?? $this->defaultSucursalId;
    }

    public function mappedOrSkip(string $entity, int|string $legacyId): ?int
    {
        if (! $this->force) {
            $existing = $this->idMap->get($entity, $legacyId);
            if ($existing !== null) {
                $this->stats->inc($entity, 'skipped');

                return $existing;
            }
        }

        return null;
    }

    public function remember(string $entity, int|string $legacyId, int $newId): void
    {
        if (! $this->dryRun) {
            $this->idMap->put($entity, $legacyId, $newId);
        }

        $this->stats->inc($entity, 'created');
    }
}
