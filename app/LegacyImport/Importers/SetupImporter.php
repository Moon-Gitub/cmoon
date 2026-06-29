<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Mappers\CondicionIvaMapper;
use App\LegacyImport\Support\LegacyImportContext;
use App\LegacyImport\Support\LegacyJsonParser;
use App\Models\Emisor;
use App\Models\Empresa;
use App\Models\PuntoVenta;
use App\Models\Sucursal;

class SetupImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'setup';
    }

    public function label(): string
    {
        return 'Empresa, sucursales y emisor AFIP';
    }

    public function import(LegacyImportContext $ctx): void
    {
        $legacy = $ctx->legacy('empresa')->where('id', $ctx->legacyEmpresaId)->first();

        if (! $legacy) {
            throw new \RuntimeException("No existe empresa legacy id={$ctx->legacyEmpresaId}");
        }

        $empresa = $ctx->empresa();

        if (! $ctx->dryRun) {
            $empresa->update([
                'razon_social' => $legacy->razon_social ?: $empresa->razon_social,
                'nombre_fantasia' => $legacy->titular ?: $empresa->nombre_fantasia,
                'cuit' => $legacy->cuit ?: $empresa->cuit,
                'condicion_iva' => CondicionIvaMapper::toCmoon($legacy->condicion_iva),
                'ingresos_brutos' => $legacy->numero_iibb ?: $empresa->ingresos_brutos,
                'inicio_actividades' => $this->parseDate($legacy->inicio_actividades) ?: $empresa->inicio_actividades,
                'domicilio' => $legacy->domicilio ?: $empresa->domicilio,
                'localidad' => $legacy->localidad ?: $empresa->localidad,
                'codigo_postal' => $legacy->codigo_postal ?: $empresa->codigo_postal,
                'telefono' => $legacy->telefono ?: $empresa->telefono,
                'email' => $legacy->mail ?: $empresa->email,
                'agente_retencion_iibb' => (bool) ($legacy->agente_retencion_iibb ?? $empresa->agente_retencion_iibb),
                'codigo_jurisdiccion_iibb' => (int) ($legacy->codigo_jurisdiccion_iibb ?? $empresa->codigo_jurisdiccion_iibb ?? 913),
                'tipo_regimen_retencion_default' => (int) ($legacy->tipo_regimen_retencion_default ?? $empresa->tipo_regimen_retencion_default ?? 101),
                'proximo_numero_recibo' => (int) ($legacy->proximo_numero_recibo ?? $empresa->proximo_numero_recibo ?? 1),
            ]);
        }

        $ctx->stats->inc('empresa', 'created');

        $almacenes = LegacyJsonParser::almacenes($legacy->almacenes ?? null);
        $stockColumns = ['stock', 'stock2', 'stock3', 'deposito', 'ameghino'];

        foreach ($almacenes as $index => $alm) {
            $stk = $alm['stkProd'];
            $nombre = $alm['det'] ?: $stk;

            $existingId = $ctx->idMap->get('sucursal', $stk);
            if ($existingId && ! $ctx->force) {
                $ctx->sucursalMap[$stk] = $existingId;
                $ctx->stats->inc('sucursal', 'skipped');
                continue;
            }

            if ($ctx->dryRun) {
                $fakeId = 1000 + $index;
                $ctx->sucursalMap[$stk] = $fakeId;
                $ctx->stats->inc('sucursal', 'created');
                continue;
            }

            $sucursal = Sucursal::firstOrCreate(
                ['empresa_id' => $ctx->empresaId, 'nombre' => $nombre],
                ['codigo' => strtoupper(substr($stk, 0, 10)), 'activa' => true],
            );

            $ctx->idMap->put('sucursal', $stk, $sucursal->id);
            $ctx->sucursalMap[$stk] = $sucursal->id;
            $ctx->stats->inc('sucursal', 'created');
        }

        $ctx->defaultSucursalId = reset($ctx->sucursalMap) ?: null;

        if (! $ctx->dryRun && $ctx->defaultSucursalId) {
            $emisor = Emisor::firstOrCreate(
                [
                    'empresa_id' => $ctx->empresaId,
                    'cuit' => $legacy->cuit ?: '00-00000000-0',
                ],
                [
                    'razon_social' => $legacy->razon_social ?: $empresa->razon_social,
                    'condicion_iva' => CondicionIvaMapper::toCmoon($legacy->condicion_iva),
                    'ingresos_brutos' => $legacy->numero_iibb,
                    'inicio_actividades' => $this->parseDate($legacy->inicio_actividades),
                    'domicilio' => $legacy->domicilio,
                    'entorno' => ($legacy->entorno_facturacion ?? '') === 'produccion' ? 'produccion' : 'homologacion',
                    'activo' => true,
                ],
            );

            $ctx->defaultEmisorId = $emisor->id;
            $ctx->stats->inc('emisor', 'created');

            foreach (LegacyJsonParser::puntosVenta($legacy->ptos_venta ?? null) as $numero) {
                $pv = PuntoVenta::firstOrCreate(
                    ['emisor_id' => $emisor->id, 'numero' => $numero],
                    ['descripcion' => "Punto {$numero}", 'activo' => true],
                );
                $ctx->puntoVentaMap[$numero] = $pv->id;
                $ctx->idMap->put('punto_venta', $numero, $pv->id);
            }
        }

        // Registrar columnas de stock conocidas aunque no estén en almacenes JSON
        foreach ($stockColumns as $col) {
            $ctx->sucursalMap[$col] ??= $ctx->defaultSucursalId;
        }
    }

    public static function createEmpresaFromLegacy(object $legacy): Empresa
    {
        return Empresa::create([
            'razon_social' => $legacy->razon_social ?: 'Empresa importada',
            'nombre_fantasia' => $legacy->titular,
            'cuit' => $legacy->cuit,
            'condicion_iva' => CondicionIvaMapper::toCmoon($legacy->condicion_iva),
            'ingresos_brutos' => $legacy->numero_iibb,
            'inicio_actividades' => $legacy->inicio_actividades ? \Carbon\Carbon::parse($legacy->inicio_actividades)->toDateString() : null,
            'domicilio' => $legacy->domicilio,
            'localidad' => $legacy->localidad,
            'codigo_postal' => $legacy->codigo_postal,
            'telefono' => $legacy->telefono,
            'email' => $legacy->mail,
            'activa' => true,
        ]);
    }
}
