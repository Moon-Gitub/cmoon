<?php

namespace App\Notifications;

use App\Models\TiendanubeIntegracion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TiendanubeSyncErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public int $errorCount,
        public array $recentErrors,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $storeName = $this->integracion->store_name ?? 'Tienda Tiendanube';

        $message = (new MailMessage)
            ->subject("⚠️ Errores de sincronización Tiendanube - {$storeName}")
            ->greeting('Hola,')
            ->line("Se detectaron **{$this->errorCount} errores** en la sincronización con Tiendanube en las últimas horas.")
            ->line("**Tienda:** {$storeName}");

        if (! empty($this->recentErrors)) {
            $message->line('**Errores recientes:**');

            foreach (array_slice($this->recentErrors, 0, 5) as $error) {
                $message->line("• [{$error['tipo']}] {$error['mensaje']}");
            }

            if (count($this->recentErrors) > 5) {
                $message->line('... y '.(count($this->recentErrors) - 5).' errores más.');
            }
        }

        $message->action('Ver logs completos', route('tiendanube.logs'))
            ->line('Revisá los logs para más detalles y corregí los problemas.')
            ->salutation('— POSMoon');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'integracion_id' => $this->integracion->id,
            'error_count' => $this->errorCount,
            'recent_errors' => $this->recentErrors,
        ];
    }
}
