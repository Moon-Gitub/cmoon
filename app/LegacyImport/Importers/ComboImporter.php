<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\ComboComponente;
use App\Models\Producto;

class ComboImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'combos';
    }

    public function label(): string
    {
        return 'Combos y componentes';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'combos')) {
            return;
        }

        foreach ($ctx->legacy('combos')->orderBy('id')->get() as $combo) {
            $productoLegacyId = $combo->id_producto ?? null;
            if (! $productoLegacyId) {
                continue;
            }

            $comboProductoId = $ctx->idMap->get('producto', $productoLegacyId);
            if (! $comboProductoId) {
                $ctx->stats->inc('combo', 'errors');
                continue;
            }

            if ($ctx->dryRun) {
                $ctx->stats->inc('combo', 'created');
                continue;
            }

            Producto::where('id', $comboProductoId)->update(['es_combo' => true]);
        }

        if (! $this->tableExists($ctx, 'combos_productos')) {
            return;
        }

        foreach ($ctx->legacy('combos_productos')->orderBy('id')->get() as $rel) {
            $comboId = $ctx->idMap->get('producto', $rel->id_combo ?? 0);
            $componenteId = $ctx->idMap->get('producto', $rel->id_producto ?? 0);

            if (! $comboId || ! $componenteId) {
                $ctx->stats->inc('combo_componente', 'errors');
                continue;
            }

            if ($this->skipIfMapped($ctx, 'combo_componente', $rel->id)) {
                continue;
            }

            if ($ctx->dryRun) {
                $ctx->remember('combo_componente', $rel->id, (int) $rel->id);
                continue;
            }

            ComboComponente::updateOrCreate(
                ['combo_id' => $comboId, 'componente_id' => $componenteId],
                ['cantidad' => (float) ($rel->cantidad ?? 1)],
            );

            $ctx->remember('combo_componente', $rel->id, $comboId);
        }
    }

    private function tableExists(LegacyImportContext $ctx, string $table): bool
    {
        return $ctx->legacy('information_schema.tables')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->exists();
    }
}
