<?php

namespace App\Services;

use App\Models\Comprobante;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class FacturaMailService
{
    public function enviarComprobante(Comprobante $c, string $email): void
    {
        $c->loadMissing(['puntoVenta', 'emisor', 'venta.items']);

        $tipo = $c->tipoNombre();
        $numero = $c->numeroFormateado();
        $neto = number_format((float) $c->neto, 2, ',', '.');
        $iva = number_format((float) $c->iva, 2, ',', '.');
        $total = number_format((float) $c->total, 2, ',', '.');
        $fecha = $c->fecha_emision?->format('d/m/Y') ?? '—';
        $receptor = e($c->receptor_nombre ?: 'Cliente');
        $cae = e((string) $c->cae);

        $html = <<<HTML
        <div style="font-family:sans-serif;max-width:560px">
          <h2 style="margin:0 0 12px">{$tipo} {$numero}</h2>
          <p>Comprobante para <strong>{$receptor}</strong></p>
          <ul>
            <li>Fecha: {$fecha}</li>
            <li>Neto: \$ {$neto}</li>
            <li>IVA: \$ {$iva}</li>
            <li><strong>Total: \$ {$total}</strong></li>
            <li>CAE: {$cae}</li>
          </ul>
          <p style="color:#666;font-size:13px">Adjuntamos el detalle. Conservá este mail como constancia.</p>
        </div>
        HTML;

        Mail::html($html, function ($message) use ($email, $tipo, $numero, $c) {
            $message->to($email)->subject("{$tipo} {$numero}");

            if (class_exists(\TCPDF::class) && View::exists('facturacion.factura')) {
                try {
                    $pdfHtml = view('facturacion.factura', ['comprobante' => $c])->render();
                    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                    $pdf->SetCreator('POSMoon');
                    $pdf->SetTitle("{$tipo} {$numero}");
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                    $pdf->AddPage();
                    $pdf->writeHTML($pdfHtml, true, false, true, false, '');
                    $message->attachData($pdf->Output('', 'S'), "{$numero}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
                } catch (\Throwable) {
                    // HTML-only si el PDF falla
                }
            }
        });
    }
}
