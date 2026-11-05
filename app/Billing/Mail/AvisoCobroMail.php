<?php

namespace App\Billing\Mail;

use App\Billing\Models\Customer;
use App\Billing\Models\Payment;
use App\Billing\Services\DunningService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AvisoCobroMail extends Mailable
{
    private const ASUNTOS = [
        'cuota_emitida' => 'Tu abono de POSMoon ya está disponible',
        'recordatorio' => 'Recordatorio: tu abono de POSMoon vence pronto',
        'vencido' => 'Tu abono de POSMoon está vencido',
        'por_suspender' => 'Aviso importante: suspensión del sistema POSMoon',
        'recibo' => 'Recibimos tu pago — POSMoon',
    ];

    public function __construct(
        public Customer $customer,
        public string $tipo,
        public array $estado,
        public ?Payment $pago = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::ASUNTOS[$this->tipo] ?? 'Estado de cuenta POSMoon');
    }

    public function content(): Content
    {
        return new Content(view: 'billing.mail.aviso', with: [
            'texto' => DunningService::texto($this->customer, $this->tipo, $this->estado, $this->pago),
            'mostrarBoton' => $this->tipo !== 'recibo',
            'url' => $this->customer->payUrl(),
        ]);
    }
}
