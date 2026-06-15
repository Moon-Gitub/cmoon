<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Mappers\CondicionIvaMapper;
use App\LegacyImport\Mappers\TipoDocumentoMapper;
use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Proveedor;

class ProveedorImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'proveedores';
    }

    public function label(): string
    {
        return 'Proveedores';
    }

    public function import(LegacyImportContext $ctx): void
    {
        foreach ($ctx->legacy('proveedores')->orderBy('id')->get() as $row) {
            if ($this->skipIfMapped($ctx, 'proveedor', $row->id)) {
                continue;
            }

            $nombre = trim((string) ($row->nombre ?? ''));
            if ($nombre === '') {
                $ctx->stats->inc('proveedor', 'errors');
                continue;
            }

            if ($ctx->dryRun) {
                $ctx->remember('proveedor', $row->id, (int) $row->id);
                continue;
            }

            $prov = Proveedor::create([
                'empresa_id' => $ctx->empresaId,
                'razon_social' => $nombre,
                'cuit' => $row->cuit ?: null,
                'condicion_iva' => CondicionIvaMapper::toCmoon($row->tipo_documento ?? 1),
                'email' => $row->email ?: null,
                'telefono' => $row->telefono ?: null,
                'domicilio' => $row->direccion ?: null,
                'localidad' => $row->localidad ?: null,
                'observaciones' => $row->observaciones ?: null,
                'activo' => true,
            ]);

            $ctx->remember('proveedor', $row->id, $prov->id);
        }
    }
}
