<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Mappers\CondicionIvaMapper;
use App\LegacyImport\Mappers\TipoDocumentoMapper;
use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Cliente;

class ClienteImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'clientes';
    }

    public function label(): string
    {
        return 'Clientes';
    }

    public function import(LegacyImportContext $ctx): void
    {
        $chunk = config('legacy.chunk_size', 200);

        $ctx->legacy('clientes')->orderBy('id')->chunk($chunk, function ($rows) use ($ctx) {
            foreach ($rows as $row) {
                if ($this->skipIfMapped($ctx, 'cliente', $row->id)) {
                    continue;
                }

                $nombre = trim(strip_tags((string) ($row->nombre ?? '')));
                if ($nombre === '') {
                    $ctx->stats->inc('cliente', 'errors');
                    continue;
                }

                $vendedorId = null;
                if (isset($row->id_vendedor)) {
                    $vendedorId = $ctx->idMap->get('user', $row->id_vendedor);
                }

                $payload = [
                    'empresa_id' => $ctx->empresaId,
                    'nombre' => $nombre,
                    'tipo_documento' => TipoDocumentoMapper::toCmoon($row->tipo_documento ?? null),
                    'documento' => $row->documento ?: null,
                    'condicion_iva' => CondicionIvaMapper::toCmoon($row->condicion_iva ?? null),
                    'email' => $row->email ?: null,
                    'telefono' => is_string($row->telefono ?? null) ? $row->telefono : null,
                    'domicilio' => $row->direccion ?: null,
                    'observaciones' => $row->observaciones ?: null,
                    'vendedor_id' => $vendedorId,
                    'activo' => true,
                ];

                if ($ctx->dryRun) {
                    $ctx->remember('cliente', $row->id, (int) $row->id);
                    continue;
                }

                $cliente = Cliente::create($payload);
                $ctx->remember('cliente', $row->id, $cliente->id);
            }
        });
    }
}
