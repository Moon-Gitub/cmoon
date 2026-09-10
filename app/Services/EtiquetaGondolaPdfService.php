<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Producto;
use Illuminate\Support\Collection;
use TCPDF;

class EtiquetaGondolaPdfService
{
    /**
     * @param  Collection<int, Producto>  $productos
     * @param  'normal'|'oferta'|'qr'|'barcode'|'combo'  $tipo
     */
    public function generar(Empresa $empresa, Collection $productos, string $tipo): string
    {
        return match ($tipo) {
            'oferta' => $this->oferta($empresa, $productos),
            'qr' => $this->qr($empresa, $productos),
            'barcode' => $this->barcode($empresa, $productos),
            'combo' => $this->combo($empresa, $productos),
            default => $this->normal($empresa, $productos),
        };
    }

    /** @param  Collection<int, Producto>  $productos */
    private function normal(Empresa $empresa, Collection $productos): string
    {
        $pdf = $this->basePdf();
        $pdf->AddPage('P', 'A4');

        $cols = 3;
        $rows = 8;
        $marginX = 8;
        $marginY = 8;
        $gapX = 3;
        $gapY = 2;
        $pageW = 210;
        $pageH = 297;
        $cellW = ($pageW - 2 * $marginX - ($cols - 1) * $gapX) / $cols;
        $cellH = ($pageH - 2 * $marginY - ($rows - 1) * $gapY) / $rows;

        $i = 0;
        $marca = $empresa->nombre_fantasia ?: $empresa->razon_social;

        foreach ($productos as $producto) {
            if ($i > 0 && $i % ($cols * $rows) === 0) {
                $pdf->AddPage('P', 'A4');
            }

            $pos = $i % ($cols * $rows);
            $col = $pos % $cols;
            $row = intdiv($pos, $cols);
            $x = $marginX + $col * ($cellW + $gapX);
            $y = $marginY + $row * ($cellH + $gapY);

            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetLineWidth(0.2);
            $pdf->RoundedRect($x, $y, $cellW, $cellH, 1.5, '1111', 'D');

            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetXY($x + 2, $y + 2);
            $pdf->Cell($cellW - 4, 4, $this->truncar((string) $marca, 36), 0, 0, 'C');

            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetXY($x + 2, $y + 7);
            $pdf->MultiCell($cellW - 4, 3.5, $this->truncar($producto->nombre, 52), 0, 'C');

            $precio = $this->precioMostrar($producto);
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->SetXY($x + 2, $y + $cellH - 16);
            $pdf->Cell($cellW - 4, 7, '$ '.$this->fmt($precio), 0, 0, 'C');

            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetFont('dejavusans', '', 6.5);
            $pdf->SetXY($x + 2, $y + $cellH - 7);
            $pdf->Cell($cellW - 4, 4, 'Cód: '.$producto->codigo, 0, 0, 'C');

            $i++;
        }

        return $pdf->Output('etiquetas-gondola.pdf', 'S');
    }

    /** @param  Collection<int, Producto>  $productos */
    private function oferta(Empresa $empresa, Collection $productos): string
    {
        $pdf = $this->basePdf();
        $marca = $empresa->nombre_fantasia ?: $empresa->razon_social;

        foreach ($productos as $idx => $producto) {
            if ($idx > 0) {
                $pdf->AddPage('L', 'A4');
            } else {
                $pdf->AddPage('L', 'A4');
            }

            // Fondo
            $pdf->SetFillColor(255, 248, 240);
            $pdf->Rect(0, 0, 297, 210, 'F');

            // Banda superior
            $pdf->SetFillColor(220, 38, 38);
            $pdf->Rect(0, 0, 297, 28, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('dejavusans', 'B', 22);
            $pdf->SetXY(0, 7);
            $pdf->Cell(297, 12, 'OFERTA', 0, 0, 'C');

            // Marca
            $pdf->SetTextColor(120, 80, 60);
            $pdf->SetFont('dejavusans', '', 12);
            $pdf->SetXY(20, 38);
            $pdf->Cell(257, 8, $this->truncar((string) $marca, 60), 0, 0, 'C');

            // Nombre
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont('dejavusans', 'B', 28);
            $pdf->SetXY(20, 55);
            $pdf->MultiCell(257, 12, $this->truncar($producto->nombre, 80), 0, 'C');

            $precio = $this->precioMostrar($producto);
            $tienePromo = $producto->promoActiva();

            if ($tienePromo && (float) $producto->precio_venta > $precio) {
                $pdf->SetTextColor(150, 150, 150);
                $pdf->SetFont('dejavusans', '', 22);
                $pdf->SetXY(20, 95);
                $pdf->Cell(257, 10, '$ '.$this->fmt((float) $producto->precio_venta), 0, 0, 'C');
                // tachado visual
                $pdf->SetDrawColor(150, 150, 150);
                $pdf->SetLineWidth(0.8);
                $midY = 100;
                $pdf->Line(100, $midY, 197, $midY);
            }

            $pdf->SetTextColor(220, 38, 38);
            $pdf->SetFont('dejavusans', 'B', 72);
            $pdf->SetXY(20, 110);
            $pdf->Cell(257, 35, '$ '.$this->fmt($precio), 0, 0, 'C');

            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetFont('dejavusans', '', 14);
            $pdf->SetXY(20, 175);
            $pdf->Cell(257, 8, 'Código: '.$producto->codigo, 0, 0, 'C');

            if ($producto->es_combo) {
                $pdf->SetFillColor(124, 58, 237);
                $pdf->RoundedRect(120, 188, 57, 10, 2, '1111', 'F');
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('dejavusans', 'B', 11);
                $pdf->SetXY(120, 189.5);
                $pdf->Cell(57, 7, 'COMBO', 0, 0, 'C');
            }
        }

        return $pdf->Output('etiquetas-oferta.pdf', 'S');
    }

    /** @param  Collection<int, Producto>  $productos */
    private function qr(Empresa $empresa, Collection $productos): string
    {
        $pdf = $this->basePdf();
        $pdf->AddPage('P', 'A4');

        $cols = 4;
        $rows = 5;
        $marginX = 10;
        $marginY = 10;
        $gapX = 4;
        $gapY = 4;
        $cellW = (210 - 2 * $marginX - ($cols - 1) * $gapX) / $cols;
        $cellH = (297 - 2 * $marginY - ($rows - 1) * $gapY) / $rows;

        $i = 0;
        foreach ($productos as $producto) {
            if ($i > 0 && $i % ($cols * $rows) === 0) {
                $pdf->AddPage('P', 'A4');
            }

            $pos = $i % ($cols * $rows);
            $col = $pos % $cols;
            $row = intdiv($pos, $cols);
            $x = $marginX + $col * ($cellW + $gapX);
            $y = $marginY + $row * ($cellH + $gapY);

            $pdf->SetDrawColor(210, 210, 210);
            $pdf->RoundedRect($x, $y, $cellW, $cellH, 1.2, '1111', 'D');

            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont('dejavusans', 'B', 7);
            $pdf->SetXY($x + 1.5, $y + 2);
            $pdf->MultiCell($cellW - 3, 3.2, $this->truncar($producto->nombre, 40), 0, 'C');

            $url = url('/consulta-precio/'.rawurlencode($producto->codigo));
            // Solo nombre + QR: el precio se consulta al escanear (dinámico).
            $qrSize = min(36, $cellW - 6);
            $qrX = $x + ($cellW - $qrSize) / 2;
            $qrY = $y + ($cellH - $qrSize) / 2 + 2;
            $pdf->write2DBarcode($url, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, [
                'border' => false,
                'padding' => 1,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => [255, 255, 255],
            ], 'N');

            $i++;
        }

        return $pdf->Output('etiquetas-qr.pdf', 'S');
    }

    /** @param  Collection<int, Producto>  $productos */
    private function barcode(Empresa $empresa, Collection $productos): string
    {
        $pdf = $this->basePdf();
        $pdf->AddPage('P', 'A4');

        $cols = 4;
        $rows = 8;
        $marginX = 8;
        $marginY = 8;
        $gapX = 3;
        $gapY = 2;
        $cellW = (210 - 2 * $marginX - ($cols - 1) * $gapX) / $cols;
        $cellH = (297 - 2 * $marginY - ($rows - 1) * $gapY) / $rows;

        $i = 0;
        foreach ($productos as $producto) {
            if ($i > 0 && $i % ($cols * $rows) === 0) {
                $pdf->AddPage('P', 'A4');
            }

            $pos = $i % ($cols * $rows);
            $col = $pos % $cols;
            $row = intdiv($pos, $cols);
            $x = $marginX + $col * ($cellW + $gapX);
            $y = $marginY + $row * ($cellH + $gapY);

            $pdf->SetDrawColor(210, 210, 210);
            $pdf->RoundedRect($x, $y, $cellW, $cellH, 1, '1111', 'D');

            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont('dejavusans', 'B', 7);
            $pdf->SetXY($x + 1, $y + 1.5);
            $pdf->MultiCell($cellW - 2, 3, $this->truncar($producto->nombre, 28), 0, 'C');

            $codigo = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $producto->codigo) ?: '0';
            $style = [
                'position' => '',
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,
                'cellfitalign' => '',
                'border' => false,
                'hpadding' => 'auto',
                'vpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'text' => true,
                'font' => 'helvetica',
                'fontsize' => 7,
                'stretchtext' => 4,
            ];
            $pdf->write1DBarcode($codigo, 'C128', $x + 2, $y + 10, $cellW - 4, 14, 0.4, $style, 'N');

            $i++;
        }

        return $pdf->Output('etiquetas-barcode.pdf', 'S');
    }

    /** @param  Collection<int, Producto>  $productos */
    private function combo(Empresa $empresa, Collection $productos): string
    {
        $combos = $productos->filter(fn (Producto $p) => $p->es_combo)->values();
        if ($combos->isEmpty()) {
            $combos = $productos;
        }

        $pdf = $this->basePdf();
        $marca = $empresa->nombre_fantasia ?: $empresa->razon_social;

        foreach ($combos as $idx => $producto) {
            $producto->loadMissing(['componentes.componente']);
            if ($idx > 0) {
                $pdf->AddPage('P', 'A4');
            } else {
                $pdf->AddPage('P', 'A4');
            }

            $pdf->SetFillColor(245, 243, 255);
            $pdf->Rect(0, 0, 210, 297, 'F');

            $pdf->SetFillColor(124, 58, 237);
            $pdf->Rect(0, 0, 210, 32, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('dejavusans', 'B', 20);
            $pdf->SetXY(0, 9);
            $pdf->Cell(210, 12, 'COMBO', 0, 0, 'C');

            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetFont('dejavusans', '', 11);
            $pdf->SetXY(15, 42);
            $pdf->Cell(180, 7, $this->truncar((string) $marca, 50), 0, 0, 'C');

            $pdf->SetTextColor(20, 20, 20);
            $pdf->SetFont('dejavusans', 'B', 22);
            $pdf->SetXY(15, 55);
            $pdf->MultiCell(180, 10, $this->truncar($producto->nombre, 70), 0, 'C');

            $pdf->SetTextColor(124, 58, 237);
            $pdf->SetFont('dejavusans', 'B', 42);
            $pdf->SetXY(15, 85);
            $pdf->Cell(180, 20, '$ '.$this->fmt($this->precioMostrar($producto)), 0, 0, 'C');

            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->SetXY(20, 115);
            $pdf->Cell(170, 8, 'Incluye:', 0, 1, 'L');

            $pdf->SetFont('dejavusans', '', 11);
            $y = 125;
            foreach ($producto->componentes as $comp) {
                $nombreComp = $comp->componente?->nombre ?? 'Ítem';
                $cant = rtrim(rtrim(number_format((float) $comp->cantidad, 3, ',', '.'), '0'), ',');
                $pdf->SetXY(25, $y);
                $pdf->Cell(160, 7, '• '.$cant.' × '.$this->truncar($nombreComp, 55), 0, 1, 'L');
                $y += 8;
                if ($y > 260) {
                    break;
                }
            }

            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetXY(15, 275);
            $pdf->Cell(180, 6, 'Código: '.$producto->codigo, 0, 0, 'C');
        }

        return $pdf->Output('etiquetas-combo.pdf', 'S');
    }

    private function basePdf(): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('POSMoon');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        return $pdf;
    }

    private function precioMostrar(Producto $producto): float
    {
        return $producto->precioGondola();
    }

    private function fmt(float $precio): string
    {
        return number_format($precio, 2, ',', '.');
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 1).'…';
    }
}
