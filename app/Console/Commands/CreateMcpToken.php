<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateMcpToken extends Command
{
    protected $signature = 'mcp:token
        {usuario : Usuario o email del operador}
        {--name=cursor-mcp : Nombre del token}
        {--revoke : Revoca tokens MCP previos de este usuario}
        {--forever : Sin fecha de vencimiento}';

    protected $description = 'Crea un token Sanctum de solo lectura (mcp:read) para el MCP de POSMoon';

    public function handle(): int
    {
        $login = (string) $this->argument('usuario');
        $user = User::query()
            ->where('usuario', $login)
            ->orWhere('email', $login)
            ->first();

        if (! $user || ! $user->activo) {
            $this->error('Usuario inexistente o inactivo.');

            return self::FAILURE;
        }

        if (! $user->empresa_id) {
            $this->error('El usuario no tiene empresa_id.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->tokens
                ->filter(fn ($token) => in_array('mcp:read', $token->abilities ?? [], true))
                ->each->delete();
        }

        $days = (int) config('posmoon_mcp.token_days', 90);
        $expires = $this->option('forever') || $days <= 0
            ? null
            : now()->addDays($days);

        $nuevo = $user->createToken((string) $this->option('name'), ['mcp:read'], $expires);

        $this->info('Token MCP creado (solo lectura). Guardalo: no se vuelve a mostrar.');
        $this->line('usuario: '.$user->usuario);
        $this->line('empresa_id: '.$user->empresa_id);
        $this->line('vence: '.($expires ? $expires->toDateTimeString() : 'nunca'));
        $this->newLine();
        $this->line($nuevo->plainTextToken);

        return self::SUCCESS;
    }
}
