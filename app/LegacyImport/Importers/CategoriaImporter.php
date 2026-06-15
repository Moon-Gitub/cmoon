<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Categoria;

class CategoriaImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'categorias';
    }

    public function label(): string
    {
        return 'Categorías de productos';
    }

    public function import(LegacyImportContext $ctx): void
    {
        $rows = $ctx->legacy('categorias')->orderBy('id')->get();

        foreach ($rows as $row) {
            if ($existing = $this->skipIfMapped($ctx, 'categoria', $row->id)) {
                continue;
            }

            $nombre = trim(strip_tags((string) $row->categoria));
            if ($nombre === '') {
                $ctx->stats->inc('categoria', 'errors');
                continue;
            }

            if ($ctx->dryRun) {
                $ctx->remember('categoria', $row->id, (int) $row->id);
                continue;
            }

            $cat = Categoria::firstOrCreate(
                ['empresa_id' => $ctx->empresaId, 'nombre' => $nombre],
                ['activa' => true],
            );

            $ctx->remember('categoria', $row->id, $cat->id);
        }
    }
}
