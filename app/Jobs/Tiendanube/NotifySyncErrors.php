<?php

namespace App\Jobs\Tiendanube;

use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\User;
use App\Notifications\TiendanubeSyncErrorNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySyncErrors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $hoursBack = 6,
        public int $minErrorsToNotify = 3,
    ) {}

    public function handle(): void
    {
        $since = now()->subHours($this->hoursBack);

        $integraciones = TiendanubeIntegracion::where('activo', true)->get();

        foreach ($integraciones as $integracion) {
            $errors = TiendanubeLog::where('integracion_id', $integracion->id)
                ->where('status', 'error')
                ->where('created_at', '>=', $since)
                ->orderByDesc('created_at')
                ->get();

            if ($errors->count() < $this->minErrorsToNotify) {
                continue;
            }

            // Buscar admins de la empresa para notificar
            $admins = User::where('empresa_id', $integracion->empresa_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->get();

            if ($admins->isEmpty()) {
                // Si no hay admins, notificar al primer usuario con permiso de empresa
                $admins = User::where('empresa_id', $integracion->empresa_id)
                    ->permission('empresa.editar')
                    ->limit(1)
                    ->get();
            }

            $recentErrors = $errors->take(10)->map(fn ($e) => [
                'tipo' => $e->tipo,
                'mensaje' => $e->mensaje,
                'created_at' => $e->created_at->toDateTimeString(),
            ])->toArray();

            foreach ($admins as $admin) {
                $admin->notify(new TiendanubeSyncErrorNotification(
                    $integracion,
                    $errors->count(),
                    $recentErrors,
                ));
            }

            // Registrar que se envió la notificación
            TiendanubeLog::registrar(
                $integracion,
                'notification',
                'push',
                mensaje: "Notificación de {$errors->count()} errores enviada a {$admins->count()} usuario(s)",
            );
        }
    }
}
