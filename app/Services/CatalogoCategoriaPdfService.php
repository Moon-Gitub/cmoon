<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use TCPDF;

class CatalogoCategoriaPdfService
{
    public function generar(Empresa $empresa, Categoria $categoria): string
    {
        $productos = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('categoria_id', $categoria->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'precio_venta']);

        $pdf = new class($empresa) extends TCPDF
        {
            public function __construct(private Empresa $empresaCfg)
            {
                parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
            }

            public function Header(): void
            {
                $bMargin = $this->getBreakMargin();
                $auto = $this->AutoPageBreak;
                $this->SetAutoPageBreak(false, 0);

                $fondo = $this->rutaPublica($this->empresaCfg->catalogo_fondo_path);
                if ($fondo) {
                    $this->Image($fondo, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
                } else {
                    $this->SetFillColor(32, 36, 40);
                    $this->Rect(0, 0, 210, 297, 'F');
                }

                $logo = $this->rutaPublica($this->empresaCfg->catalogo_logo_path)
                    ?: $this->rutaPublica($this->empresaCfg->logo_path);
                if ($logo) {
                    $this->Image($logo, 12, 12, 40, 0, '', '', '', false, 300, '', false, false, 0);
                }

                $this->SetAutoPageBreak($auto, $bMargin);
                $this->setPageMark();
            }

            private function rutaPublica(?string $path): ?string
            {
                if (! $path) {
                    return null;
                }
                $full = storage_path('app/public/'.$path);
                return is_file($full) ? $full : null;
            }
        };

        $pdf->SetCreator('POSMoon');
        $pdf->SetAuthor($empresa->nombre_fantasia ?: $empresa->razon_social);
        $pdf->SetTitle('Catálogo — '.$categoria->nombre);
        $pdf->SetMargins(15, 28, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->AddPage();

        $tituloColor = $this->hexToRgb($empresa->catalogo_color_titulo ?: '#909e23');
        $textoColor = $this->hexToRgb($empresa->catalogo_color_texto ?: '#f1f0ec');

        $titulo = e(mb_strtoupper($categoria->nombre));
        $hexTitulo = $empresa->catalogo_color_titulo ?: '#909e23';
        $hexTexto = $empresa->catalogo_color_texto ?: '#f1f0ec';

        $html = <<<HTML
<table cellpadding="3" cellspacing="0" style="width:100%;">
    <tr>
        <td style="width:38%;"></td>
        <td style="width:62%; border-bottom:3px solid {$hexTitulo}; color:#ffffff; font-size:14px; font-weight:bold; letter-spacing:1px;">
            {$titulo}
        </td>
    </tr>
    <tr><td colspan="2" style="height:8px;"></td></tr>
HTML;

        foreach ($productos as $producto) {
            $nombre = e(mb_strtoupper($producto->nombre));
            $precio = '$'.number_format((float) $producto->precio_venta, 0, ',', '.');
            $html .= <<<HTML
    <tr style="color:{$hexTexto};">
        <td style="width:38%;"></td>
        <td style="width:47%; font-size:11px;">{$nombre}</td>
        <td style="width:15%; font-size:12px; font-weight:bold; text-align:right;">{$precio}</td>
    </tr>
HTML;
        }

        if ($productos->isEmpty()) {
            $html .= <<<HTML
    <tr style="color:{$hexTexto};">
        <td style="width:38%;"></td>
        <td colspan="2" style="font-size:11px;">Sin productos activos en esta categoría.</td>
    </tr>
HTML;
        }

        $html .= '</table>';

        // Avoid unused vars lint - colors applied via hex in HTML
        unset($tituloColor, $textoColor);

        $pdf->writeHTML($html, true, false, false, false, '');

        return $pdf->Output('catalogo-'.$categoria->id.'.pdf', 'S');
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [241, 240, 236];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
