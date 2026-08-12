<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Mappers\CondicionIvaMapper;
use App\LegacyImport\Mappers\TipoDocumentoMapper;
use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Cliente;
use App\Models\ListaPrecio;

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
                    'documento' => ($row->documento ?? null) ?: null,
                    'condicion_iva' => CondicionIvaMapper::toCmoon($row->condicion_iva ?? null),
                    'email' => ($row->email ?? null) ?: null,
                    'telefono' => is_string($row->telefono ?? null) ? $row->telefono : null,
                    'domicilio' => ($row->direccion ?? null) ?: null,
                    'observaciones' => ($row->observaciones ?? null) ?: null,
                    'vendedor_id' => $vendedorId,
                    'lista_precio_id' => $this->resolverListaPrecio($ctx, $row),
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

    private function resolverListaPrecio(LegacyImportContext $ctx, object $row): ?int
    {
        $codigo = trim((string) ($row->tipoPrecio ?? $row->lista_precio ?? ''));
        if ($codigo === '' || $codigo === '0' || $codigo === 'precio_venta') {
            return null;
        }

        // demonew: tipoPrecio = precioCosto / precio_compra → lista "Precio Costo"
        if (in_array($codigo, ['precioCosto', 'precio_compra'], true)) {
            $idCosto = ListaPrecio::query()
                ->where('empresa_id', $ctx->empresaId)
                ->where(function ($q) {
                    $q->where('base', 'compra')
                        ->orWhere('nombre', 'like', '%Costo%');
                })
                ->orderBy('id')
                ->value('id');

            return $idCosto ? (int) $idCosto : null;
        }

        if ($this->tableExists($ctx, 'listas_precio')) {
            $legacyLista = $ctx->legacy('listas_precio')->where('codigo', $codigo)->first();
            if ($legacyLista) {
                $mapped = $ctx->idMap->get('lista_precio', $legacyLista->id);
                if ($mapped) {
                    return $mapped;
                }

                $porNombre = ListaPrecio::query()
                    ->where('empresa_id', $ctx->empresaId)
                    ->where('nombre', $legacyLista->nombre)
                    ->value('id');
                if ($porNombre) {
                    return (int) $porNombre;
                }
            }
        }

        // Fallback por código conocido (empleados / trabajadores_…)
        $nombre = match ($codigo) {
            'empleados' => 'Empleados',
            'trabajadores_valle_grande' => 'Trabajadores Valle Grande',
            default => null,
        };
        if ($nombre === null) {
            return null;
        }

        $id = ListaPrecio::query()
            ->where('empresa_id', $ctx->empresaId)
            ->where('nombre', $nombre)
            ->value('id');

        return $id ? (int) $id : null;
    }
}
