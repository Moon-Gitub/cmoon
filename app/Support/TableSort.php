<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class TableSort
{
    /**
     * Aplica orderBy según ?sort=&dir= y devuelve [$sort, $dir] para la vista.
     *
     * @param  Builder|Relation  $query  Builder Eloquent o relación (HasMany, MorphMany, etc.)
     * @param  array<string, string|callable>  $columns  clave => columna SQL o fn(Builder|Relation, string $dir): void
     * @return array{0: string, 1: string}
     */
    public static function apply(
        Builder|Relation $query,
        Request $request,
        array $columns,
        string $default,
        string $defaultDir = 'asc',
    ): array {
        $sort = (string) $request->input('sort', $default);
        $dir = strtolower((string) $request->input('dir', $defaultDir)) === 'desc' ? 'desc' : 'asc';

        if (! array_key_exists($sort, $columns)) {
            $sort = $default;
            $dir = strtolower($defaultDir) === 'desc' ? 'desc' : 'asc';
        }

        $column = $columns[$sort];

        if (is_callable($column)) {
            $column($query, $dir);
        } else {
            $query->orderBy($column, $dir);
        }

        return [$sort, $dir];
    }
}
