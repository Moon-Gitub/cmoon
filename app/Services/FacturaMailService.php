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

        $textoPlano = implode("\n", [
            "{$tipo} {$numero}",
            'Receptor: '.$c->receptor_nombre,
            "Fecha: {$fecha}",
            "Neto: $ {$neto}",
            "IVA: $ {$iva}",
            "Total: $ {$total}",
            'CAE: '.$c->cae,
        ]);

        $pdfBytes = null;
        if (class_exists(\TCPDF::class) && View::exists('facturacion.factura')) {
            try {
                $pdfHtml = $this->sanitizarHtmlPdf(
                    view('facturacion.factura', ['comprobante' => $c])->render()
                );

                $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('POSMoon');
                $pdf->SetTitle("{$tipo} {$numero}");
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetMargins(10, 10, 10);
                $pdf->SetAutoPageBreak(true, 10);
                $pdf->AddPage();
                $pdf->writeHTMLCell(0, 0, '', '', $pdfHtml, 0, 1, false, true, '', true);
                $pdfBytes = $pdf->Output('', 'S');
            } catch (\Throwable) {
                $pdfBytes = null;
            }
        }

        Mail::html($html, function ($message) use ($email, $tipo, $numero, $pdfBytes, $textoPlano) {
            $message->to($email)->subject("{$tipo} {$numero}");

            if ($pdfBytes !== null && $pdfBytes !== '') {
                $message->attachData($pdfBytes, "{$numero}.pdf", [
                    'mime' => 'application/pdf',
                ]);
            } else {
                $message->attachData($textoPlano, "{$numero}.txt", [
                    'mime' => 'text/plain',
                ]);
            }
        });
    }

    private function sanitizarHtmlPdf(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;

        return $html;
    }
}
