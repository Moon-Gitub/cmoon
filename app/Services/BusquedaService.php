<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;

class BusquedaService
{
    /**
     * Búsqueda predictiva de productos (estilo demonew: multi-campo, prioriza código exacto).
     *
     * @return list<array<string, mixed>>
     */
    public function productos(string $q, int $limit = 20, bool $soloActivos = true, bool $excluirCombos = false): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $limit = min(max($limit, 1), 40);
        $query = Producto::query()->with('categoria:id,nombre');

        if ($soloActivos) {
            $query->where('activo', true);
        }
        if ($excluirCombos) {
            $query->where('es_combo', false);
        }

        $this->aplicarTerminos($query, $q, ['codigo', 'nombre'], preferExact: ['codigo']);

        return $query
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END', [$q, $q.'%'])
            ->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'label' => trim(($p->codigo ? $p->codigo.' — ' : '').$p->nombre),
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'precio_venta' => (float) $p->precio_venta,
                'precio_compra' => (float) $p->precio_compra,
                'alicuota_iva' => (float) $p->alicuota_iva,
                'categoria' => $p->categoria?->nombre,
                'url' => route('productos.edit', $p),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function clientes(string $q, int $limit = 20, bool $soloActivos = true): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $limit = min(max($limit, 1), 40);
        $query = Cliente::query();

        if ($soloActivos) {
            $query->where('activo', true);
        }

        $this->aplicarTerminos($query, $q, ['nombre', 'documento', 'email', 'telefono'], preferExact: ['documento']);

        return $query
            ->orderByRaw('CASE WHEN documento = ? THEN 0 WHEN documento LIKE ? THEN 1 ELSE 2 END', [$q, $q.'%'])
            ->orderBy('nombre')
            ->limit($limit)
            ->get(['id', 'nombre', 'tipo_documento', 'documento', 'email', 'telefono', 'lista_precio_id', 'condicion_iva'])
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'label' => $c->nombre.($c->documento ? ' — '.$c->tipo_documento.' '.$c->documento : ''),
                'nombre' => $c->nombre,
                'documento' => $c->documento,
                'tipo_documento' => $c->tipo_documento,
                'condicion_iva' => $c->condicion_iva,
                'email' => $c->email,
                'telefono' => $c->telefono,
                'url' => route('clientes.show', $c),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function proveedores(string $q, int $limit = 20, bool $soloActivos = true): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $limit = min(max($limit, 1), 40);
        $query = Proveedor::query();

        if ($soloActivos) {
            $query->where('activo', true);
        }

        $this->aplicarTerminos($query, $q, ['razon_social', 'cuit', 'email', 'telefono'], preferExact: ['cuit']);

        return $query
            ->orderByRaw('CASE WHEN cuit = ? THEN 0 WHEN cuit LIKE ? THEN 1 ELSE 2 END', [$q, $q.'%'])
            ->orderBy('razon_social')
            ->limit($limit)
            ->get(['id', 'razon_social', 'cuit', 'email', 'telefono', 'alicuota_retencion_iibb'])
            ->map(fn (Proveedor $p) => [
                'id' => $p->id,
                'label' => $p->razon_social.($p->cuit ? ' — '.$p->cuit : ''),
                'razon_social' => $p->razon_social,
                'cuit' => $p->cuit,
                'email' => $p->email,
                'telefono' => $p->telefono,
                'alicuota_retencion_iibb' => (float) ($p->alicuota_retencion_iibb ?? 0),
                'url' => route('proveedores.edit', $p),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ventas(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 1) {
            return [];
        }

        $limit = min(max($limit, 1), 40);
        $query = Venta::query()->with('cliente:id,nombre,documento');

        $numero = (int) preg_replace('/\D/', '', $q);
        $query->where(function (Builder $outer) use ($q, $numero) {
            if ($numero > 0) {
                $outer->where('ventas.numero', $numero)
                    ->orWhere('ventas.numero', 'like', $numero.'%');
            }
            $outer->orWhereHas('cliente', function (Builder $c) use ($q) {
                $this->aplicarTerminos($c, $q, ['nombre', 'documento']);
            });
        });

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Venta $v) => [
                'id' => $v->id,
                'label' => '#'.str_pad((string) $v->numero, 6, '0', STR_PAD_LEFT)
                    .' — '.($v->cliente?->nombre ?? 'Consumidor final')
                    .' — $ '.number_format((float) $v->total, 2, ',', '.'),
                'numero' => $v->numero,
                'fecha' => $v->fecha?->format('d/m/Y H:i'),
                'total' => (float) $v->total,
                'cliente' => $v->cliente?->nombre,
                'url' => route('ventas.show', $v),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comprobantes(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $limit = min(max($limit, 1), 40);
        $query = Comprobante::query()->with('puntoVenta:id,numero');

        $numero = (int) preg_replace('/\D/', '', $q);
        $query->where(function (Builder $outer) use ($q, $numero) {
            if ($numero > 0) {
                $outer->where(function (Builder $n) use ($numero) {
                    $n->where('numero', $numero)
                        ->orWhere('numero', 'like', $numero.'%')
                        ->orWhere('doc_numero', 'like', '%'.$this->escaparLike((string) $numero).'%');
                });
            }
            $outer->orWhere(function (Builder $text) use ($q) {
                $this->aplicarTerminos($text, $q, ['receptor_nombre', 'cae', 'doc_numero'], preferExact: ['cae']);
            });
        });

        return $query
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Comprobante $c) => [
                'id' => $c->id,
                'label' => ($c->numero ? $c->numeroFormateado() : 'Sin nº')
                    .' — '.($c->receptor_nombre ?: 'Sin receptor')
                    .' — $ '.number_format((float) $c->total, 2, ',', '.'),
                'numero' => $c->numero,
                'receptor' => $c->receptor_nombre,
                'cae' => $c->cae,
                'url' => route('facturacion.show', $c),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usuarios(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $limit = min(max($limit, 1), 40);
        $query = User::query()->where('empresa_id', auth()->user()->empresa_id);

        $this->aplicarTerminos($query, $q, ['name', 'usuario', 'email']);

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'usuario', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'label' => $u->name.($u->usuario ? ' — @'.$u->usuario : ''),
                'nombre' => $u->name,
                'usuario' => $u->usuario,
                'email' => $u->email,
                'url' => route('usuarios.edit', $u),
            ])
            ->all();
    }

    /**
     * Aplica búsqueda combinada: cada palabra debe matchear en algún campo (AND entre tokens, OR entre campos).
     * Si hay campos "exactos" (código/doc), también prueba igualdad y prefijo primero vía where anidado.
     *
     * @param  list<string>  $campos
     * @param  list<string>  $preferExact
     */
    public function aplicarTerminos(Builder $query, string $q, array $campos, array $preferExact = []): void
    {
        $tokens = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            $like = '%'.$this->escaparLike($token).'%';
            $prefijo = $this->escaparLike($token).'%';

            $query->where(function (Builder $group) use ($campos, $preferExact, $token, $like, $prefijo) {
                foreach ($campos as $campo) {
                    $group->orWhere($campo, 'like', $like);
                }
                foreach ($preferExact as $campo) {
                    $group->orWhere($campo, $token)
                        ->orWhere($campo, 'like', $prefijo);
                }
            });
        }
    }

    private function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}
