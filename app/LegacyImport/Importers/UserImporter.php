<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Mappers\RolMapper;
use App\LegacyImport\Support\LegacyImportContext;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Usuarios y roles';
    }

    public function import(LegacyImportContext $ctx): void
    {
        $query = $ctx->legacy('usuarios')->orderBy('id');

        if ($this->hasColumn($ctx, 'empresa')) {
            $query->where('empresa', $ctx->legacyEmpresaId);
        }

        foreach ($query->get() as $row) {
            if ($this->skipIfMapped($ctx, 'user', $row->id)) {
                if ($ctx->defaultUserId === null) {
                    $ctx->defaultUserId = $ctx->idMap->get('user', $row->id);
                }
                continue;
            }

            $usuario = trim((string) $row->usuario);
            if ($usuario === '') {
                $ctx->stats->inc('user', 'errors');
                continue;
            }

            $email = Str::slug($usuario).'@legacy-import.local';
            $sucursalId = $ctx->resolveSucursal($row->sucursal ?? null);

            if ($ctx->dryRun) {
                $ctx->remember('user', $row->id, (int) $row->id);
                $ctx->defaultUserId ??= (int) $row->id;
                continue;
            }

            $user = User::updateOrCreate(
                ['usuario' => $usuario],
                [
                    'name' => $row->nombre ?: $usuario,
                    'email' => User::where('email', $email)->where('usuario', '!=', $usuario)->exists()
                        ? Str::slug($usuario)."-{$row->id}@legacy-import.local"
                        : $email,
                    'password' => $this->resolvePassword($row->password),
                    'empresa_id' => $ctx->empresaId,
                    'sucursal_id' => $sucursalId,
                    'activo' => (int) ($row->estado ?? 1) === 1,
                    'ultimo_acceso_at' => $this->parseDateTime($row->ultimo_login ?? null),
                ],
            );

            $roleName = RolMapper::toSpatie($row->perfil ?? null);
            if ($role = Role::findByName($roleName, 'web')) {
                $user->syncRoles([$role]);
            }

            $ctx->remember('user', $row->id, $user->id);
            $ctx->defaultUserId ??= $user->id;
        }

        $ctx->defaultUserId ??= User::where('empresa_id', $ctx->empresaId)->value('id');
    }

    private function resolvePassword(?string $hash): string
    {
        if ($hash && str_starts_with($hash, '$2y$')) {
            return $hash;
        }

        return bcrypt('CMoon2026!');
    }

    private function hasColumn(LegacyImportContext $ctx, string $column): bool
    {
        return $ctx->legacy('information_schema.columns')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', 'usuarios')
            ->where('COLUMN_NAME', $column)
            ->exists();
    }
}
