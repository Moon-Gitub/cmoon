<?php

namespace App\Billing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Alertas internas para el administrador de Moon (mail y/o Slack). */
class AdminNotifier
{
    public function enviar(string $asunto, string $cuerpo): void
    {
        Log::channel(config('logging.default'))->warning("[Moon Cobros] {$asunto}");

        if ($email = config('billing.avisos.admin_email')) {
            try {
                Mail::raw($cuerpo, fn ($m) => $m->to($email)->subject($asunto));
            } catch (Throwable $e) {
                Log::error('Moon Cobros: no se pudo enviar mail al admin', ['error' => $e->getMessage()]);
            }
        }

        if ($slack = config('billing.avisos.slack_webhook')) {
            try {
                Http::timeout(10)->post($slack, ['text' => "*{$asunto}*\n```".mb_substr($cuerpo, 0, 3500).'```']);
            } catch (Throwable $e) {
                Log::error('Moon Cobros: no se pudo avisar por Slack', ['error' => $e->getMessage()]);
            }
        }
    }
}
