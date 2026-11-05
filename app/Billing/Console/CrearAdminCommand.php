<?php

namespace App\Billing\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CrearAdminCommand extends Command
{
    protected $signature = 'billing:admin {email} {--nombre=Administrador Moon}';

    protected $description = 'Crea o actualiza un superadmin del panel de cobros (muestra la contraseña una sola vez).';

    public function handle(): int
    {
        $password = Str::password(20, symbols: false);
        $user = User::where('email', $this->argument('email'))->first() ?? new User(['email' => $this->argument('email')]);
        $user->forceFill([
            'name' => $user->name ?: $this->option('nombre'),
            'usuario' => $user->usuario ?: Str::before($user->email, '@'),
            'password' => Hash::make($password),
            'activo' => true,
            'es_superadmin' => true,
        ])->save();

        $this->info("Superadmin {$user->email} listo. Contraseña (guardala ahora): {$password}");

        return self::SUCCESS;
    }
}
