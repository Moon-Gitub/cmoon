<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Services\CatalogoCategoriaPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CatalogoCategoriaController extends Controller
{
    public function pdf(Categoria $categoria, CatalogoCategoriaPdfService $servicio): SymfonyResponse
    {
        abort_unless(auth()->user()->can('categorias.ver'), 403);
        abort_unless((int) $categoria->empresa_id === (int) auth()->user()->empresa_id, 404);

        $empresa = Empresa::findOrFail($categoria->empresa_id);

        return $this->stream($servicio, $empresa, $categoria);
    }

    public function publico(string $token, Categoria $categoria, CatalogoCategoriaPdfService $servicio): SymfonyResponse
    {
        $empresa = Empresa::query()
            ->where('catalogo_share_token', $token)
            ->where('activa', true)
            ->firstOrFail();

        abort_unless((int) $categoria->empresa_id === (int) $empresa->id, 404);
        abort_unless($categoria->activa, 404);

        return $this->stream($servicio, $empresa, $categoria);
    }

    public static function asegurarToken(Empresa $empresa): string
    {
        if (filled($empresa->catalogo_share_token)) {
            return $empresa->catalogo_share_token;
        }

        $empresa->forceFill([
            'catalogo_share_token' => Str::random(40),
        ])->save();

        return $empresa->catalogo_share_token;
    }

    private function stream(CatalogoCategoriaPdfService $servicio, Empresa $empresa, Categoria $categoria): SymfonyResponse
    {
        $binario = $servicio->generar($empresa, $categoria);
        $nombre = 'catalogo-'.Str::slug($categoria->nombre).'.pdf';

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
