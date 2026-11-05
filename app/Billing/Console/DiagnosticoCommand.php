<?php

namespace App\Billing\Console;

use App\Billing\Services\MercadoPagoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnosticoCommand extends Command
{
    protected $signature = 'billing:diagnostico';

    protected $description = 'Verifica configuración del hub: Mercado Pago, firma, base legacy, mail y WhatsApp.';

    public function handle(MercadoPagoClient $mp): int
    {
        $ok = true;
        $check = function (string $nombre, bool $estado, string $detalle = '') use (&$ok) {
            $ok = $ok && $estado;
            $this->line(($estado ? '<info>OK</info>  ' : '<error>FALLA</error> ').$nombre.($detalle ? " — {$detalle}" : ''));
        };

        try {
            $yo = $mp->cuentaPropia();
            $collector = (string) config('billing.mercadopago.collector_id');
            $check('Token Mercado Pago', true, 'cuenta '.($yo['nickname'] ?? '?').' id '.($yo['id'] ?? '?'));
            $check('MP_COLLECTOR_ID coincide con el token', $collector !== '' && $collector === (string) ($yo['id'] ?? ''), $collector === '' ? 'falta MP_COLLECTOR_ID' : '');
        } catch (Throwable $e) {
            $check('Token Mercado Pago', false, $e->getMessage());
        }

        $check('MP_WEBHOOK_SECRET configurado', (string) config('billing.mercadopago.webhook_secret') !== '');
        $check('URL pública https', str_starts_with((string) config('billing.public_url'), 'https://'), (string) config('billing.public_url'));

        try {
            $n = DB::connection(config('billing.legacy.connection'))->table('clientes')->count();
            $check('Base legacy (solo lectura)', true, "{$n} clientes");
        } catch (Throwable $e) {
            $check('Base legacy (solo lectura)', false, 'no configurada o inaccesible (solo necesaria para importar)');
        }

        $check('Mail configurado', config('mail.default') !== 'log' && config('mail.default') !== 'array', (string) config('mail.default'));
        $check('WhatsApp (YCloud)', (bool) config('ycloud.api_key') && (bool) config('ycloud.phone_from'));

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
