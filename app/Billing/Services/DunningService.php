<?php

namespace App\Billing\Services;

use App\Billing\Mail\AvisoCobroMail;
use App\Billing\Models\Customer;
use App\Billing\Models\Notification;
use App\Billing\Models\Payment;
use App\Billing\Support\Money;
use App\Services\YCloudService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisos de cobranza por mail y WhatsApp. Idempotente: UNIQUE(customer, tipo, periodo, canal).
 */
class DunningService
{
    public function __construct(
        private LedgerService $ledger,
        private CustomerStatusService $status,
    ) {}

    /** @return array{enviados: int, omitidos: int, fallidos: int, tipo: ?string} */
    public function ejecutar(CarbonInterface $hoy): array
    {
        $r = ['enviados' => 0, 'omitidos' => 0, 'fallidos' => 0, 'tipo' => null];
        if (! config('billing.avisos.enabled')) {
            return $r;
        }

        $tipo = config('billing.avisos.cronograma')[(int) $hoy->day] ?? null;
        if (! $tipo) {
            return $r;
        }
        $r['tipo'] = $tipo;
        $tol = (int) config('billing.reglas.tolerancia_centavos');
        $saldos = $this->ledger->saldos();

        Customer::where('estado', 'activo')->orderBy('id')->each(function (Customer $c) use ($tipo, $hoy, $tol, $saldos, &$r) {
            if (($saldos[$c->id] ?? 0) <= $tol) {
                return;
            }
            if ($tipo === 'por_suspender' && $c->modo_cobro !== 'bloqueo') {
                return;
            }

            $estado = $this->status->estado($c, $hoy, $saldos[$c->id]);
            foreach ($this->enviar($c, $tipo, $hoy->format('Y-m'), $estado) as $resultado) {
                $r[$resultado]++;
            }
        });

        return $r;
    }

    /**
     * Quién habría que avisar, sin enviar nada.
     * Si el día está en el cronograma, el texto es el de ese día (el 22 solo
     * entra en el automático si el cliente está en modo bloqueo). Si no, lista
     * igual a los que superan la tolerancia para mandarlos a mano.
     *
     * @return array{dia: int, programado: ?string, filas: list<array>}
     */
    public function cola(CarbonInterface $hoy): array
    {
        $programado = config('billing.avisos.cronograma')[(int) $hoy->day] ?? null;
        $tol = (int) config('billing.reglas.tolerancia_centavos');
        $saldos = $this->ledger->saldos();
        $filas = [];

        foreach (Customer::where('estado', 'activo')->orderBy('nombre')->get() as $c) {
            $saldo = (int) ($saldos[$c->id] ?? 0);
            if ($saldo <= $tol) {
                continue;
            }
            $estado = $this->status->estado($c, $hoy, $saldo);
            $automatico = $programado !== null && ! ($programado === 'por_suspender' && $c->modo_cobro !== 'bloqueo');
            $tipo = $automatico ? $programado : $this->tipoManual($estado['nivel'] ?? 'vencido');
            $cel = self::celularArgentino((string) $c->telefono);
            $texto = self::texto($c, $tipo, $estado);
            $filas[] = [
                'customer' => $c,
                'saldo' => $saldo,
                'nivel' => $estado['nivel'] ?? '',
                'tipo' => $tipo,
                'automatico' => $automatico,
                'texto' => $texto,
                'whatsapp' => $cel ? 'https://wa.me/'.$cel.'?text='.rawurlencode($texto) : null,
            ];
        }

        usort($filas, fn ($a, $b) => $b['saldo'] <=> $a['saldo']);

        return ['dia' => (int) $hoy->day, 'programado' => $programado, 'filas' => $filas];
    }

    private function tipoManual(string $nivel): string
    {
        return match ($nivel) {
            'pendiente' => 'recordatorio',
            'por_suspender' => 'por_suspender',
            default => 'vencido',
        };
    }

    public function enviarRecibo(Customer $c, Payment $p): void
    {
        $this->enviar($c, 'recibo', 'pago:'.$p->id, $this->status->estado($c), $p);
    }

    /** @return list<string> enviados|omitidos|fallidos por canal */
    public function enviar(Customer $c, string $tipo, string $periodo, array $estado, ?Payment $pago = null): array
    {
        $resultados = [];

        foreach (config('billing.avisos.canales', []) as $canal) {
            $destino = $this->destino($c, $canal);
            if (! $destino) {
                continue;
            }

            try {
                $n = Notification::create([
                    'customer_id' => $c->id, 'tipo' => $tipo, 'periodo' => $periodo,
                    'canal' => $canal, 'destino' => $destino, 'estado' => 'enviando',
                ]);
            } catch (UniqueConstraintViolationException) {
                $resultados[] = 'omitidos';

                continue;
            }

            try {
                $canal === 'mail'
                    ? Mail::to($destino)->send(new AvisoCobroMail($c, $tipo, $estado, $pago))
                    : $this->whatsapp($c, $destino, $tipo, $estado, $pago);
                $n->update(['estado' => 'enviado']);
                $resultados[] = 'enviados';
            } catch (Throwable $e) {
                $n->update(['estado' => 'fallido', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
                $resultados[] = 'fallidos';
            }
        }

        return $resultados;
    }

    /** Mensaje corto para el botón de WhatsApp del panel. */
    public static function textoLinkPago(Customer $c, int $saldoCentavos): string
    {
        $nombre = trim(strtok($c->nombre, '(') ?: $c->nombre);

        return "Hola {$nombre}. Tu saldo de POSMoon es ".Money::formato($saldoCentavos)
            .'. Pagalo en un minuto por este link: '.$c->payUrl();
    }

    public static function texto(Customer $c, string $tipo, array $estado, ?Payment $pago = null): string
    {
        $total = Money::formato(($estado['saldo'] ?? 0) + ($estado['recargo'] ?? 0));
        $link = $c->payUrl();
        $vto = config('billing.reglas.dia_vencimiento');
        $susp = config('billing.reglas.dia_suspension');

        return match ($tipo) {
            'cuota_emitida' => "Hola {$c->nombre}. Ya está disponible tu abono de POSMoon. Total: {$total}. Vence el día {$vto}. Pagalo en 1 minuto: {$link}",
            'recordatorio' => "Hola {$c->nombre}. Te recordamos que el abono de POSMoon vence el día {$vto}. Total: {$total}. Pagar: {$link}",
            'vencido' => "Hola {$c->nombre}. Tu abono de POSMoon está vencido. Total a hoy: {$total}. Pagá acá: {$link}",
            'por_suspender' => "Hola {$c->nombre}. Tu abono de POSMoon sigue impago ({$total}). El día {$susp} se suspende el sistema. Evitalo pagando acá: {$link}",
            'recibo' => "¡Gracias {$c->nombre}! Recibimos tu pago de ".Money::formato($pago?->importe_centavos ?? 0).'. Saldo actual: '.Money::formato(max(0, $estado['saldo'] ?? 0)).'.',
            default => "Hola {$c->nombre}. Estado de tu cuenta POSMoon: {$total}. {$link}",
        };
    }

    private function whatsapp(Customer $c, string $destino, string $tipo, array $estado, ?Payment $pago): void
    {
        $plantilla = config("billing.avisos.whatsapp_templates.{$tipo}");
        $yc = YCloudService::make();

        $resp = $plantilla
            ? $yc->sendTemplate($destino, $plantilla, [$c->nombre, Money::formato(($estado['saldo'] ?? 0) + ($estado['recargo'] ?? 0)), $c->payUrl()])
            : $yc->sendText($destino, self::texto($c, $tipo, $estado, $pago));

        if (isset($resp['error']) || (isset($resp['ok']) && ! $resp['ok'])) {
            throw new \RuntimeException('YCloud: '.json_encode($resp['error'] ?? $resp));
        }
    }

    /** "(260) 469-0410" → "5492604690410" (formato WhatsApp para móviles de Argentina). */
    public static function celularArgentino(string $telefono): ?string
    {
        $d = ltrim((string) preg_replace('/\D/', '', $telefono), '0');
        if (str_starts_with($d, '549') && strlen($d) === 13) {
            return $d;
        }
        if (str_starts_with($d, '54') && strlen($d) === 12) {
            return '549'.substr($d, 2);
        }

        return strlen($d) === 10 ? '549'.$d : null;
    }

    private function destino(Customer $c, string $canal): ?string
    {
        if ($canal === 'mail') {
            return $c->email && filter_var($c->email, FILTER_VALIDATE_EMAIL) ? $c->email : null;
        }
        if ($canal === 'whatsapp' && config('ycloud.api_key') && config('ycloud.phone_from')) {
            return self::celularArgentino((string) $c->telefono);
        }

        return null;
    }
}
