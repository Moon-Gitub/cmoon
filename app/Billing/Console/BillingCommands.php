<?php

namespace App\Billing\Console;

final class BillingCommands
{
    /** @return list<class-string> */
    public static function all(): array
    {
        return [
            GenerarCuotasCommand::class,
            ConciliarCommand::class,
            SincronizarPendientesCommand::class,
            AvisosCommand::class,
            ImportarLegacyCommand::class,
            CrearAdminCommand::class,
            ApiKeyCommand::class,
            DiagnosticoCommand::class,
        ];
    }
}
