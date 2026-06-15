<?php

namespace App\LegacyImport\Support;

use Illuminate\Support\Facades\DB;

class IdMap
{
    public function __construct(
        private readonly int $empresaId,
    ) {}

    public function get(string $entity, int|string $legacyId): ?int
    {
        $row = DB::table('legacy_import_maps')
            ->where('empresa_id', $this->empresaId)
            ->where('entity', $entity)
            ->where('legacy_id', (string) $legacyId)
            ->first();

        return $row ? (int) $row->new_id : null;
    }

    public function put(string $entity, int|string $legacyId, int $newId): void
    {
        DB::table('legacy_import_maps')->updateOrInsert(
            [
                'empresa_id' => $this->empresaId,
                'entity' => $entity,
                'legacy_id' => (string) $legacyId,
            ],
            [
                'new_id' => $newId,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function forgetEntity(string $entity): int
    {
        return DB::table('legacy_import_maps')
            ->where('empresa_id', $this->empresaId)
            ->where('entity', $entity)
            ->delete();
    }

    public function forgetAll(): int
    {
        return DB::table('legacy_import_maps')
            ->where('empresa_id', $this->empresaId)
            ->delete();
    }
}
