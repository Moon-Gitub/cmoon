<?php

namespace App\Http\Controllers;

use App\Support\ClientAppDownloads;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DescargasController extends Controller
{
    public function index(): View
    {
        $apps = ClientAppDownloads::make()->all();

        return view('descargas.index', compact('apps'));
    }

    public function download(string $platform): BinaryFileResponse|Response
    {
        abort_unless(in_array($platform, ['windows', 'linux', 'android'], true), 404);

        $file = ClientAppDownloads::make()->resolve($platform);
        abort_unless($file, 404);

        return response()->download($file['path'], $file['filename']);
    }
}
