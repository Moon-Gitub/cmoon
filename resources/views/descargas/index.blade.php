@extends('layouts.app')

@section('titulo', 'Descargar apps')

@section('contenido')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Descargar apps</h2>
        <p class="mt-1 text-sm text-slate-600">
            Instaladores oficiales de POSMoon. Siempre se ofrece la última versión publicada en el servidor.
        </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            'windows' => ['label' => 'Windows', 'icon' => 'M4 5h16v14H4z', 'cta' => 'Descargar para Windows', 'description' => 'POSMoon Offline para PC con Windows 10/11 (64 bits).'],
            'linux' => ['label' => 'Linux', 'icon' => 'M12 3c-4.5 0-8 3.5-8 8 0 3.2 1.9 6 4.7 7.2-.2-.8-.3-1.6-.3-2.4 0-3.3 2.7-6 6-6s6 2.7 6 6c0 .8-.1 1.6-.3 2.4 2.8-1.2 4.7-4 4.7-7.2 0-4.5-3.5-8-8-8z', 'cta' => 'Descargar para Linux', 'description' => 'POSMoon Offline para Linux (AppImage o paquete .deb).'],
            'android' => ['label' => 'Android', 'icon' => 'M7 4h10v16H7z', 'cta' => 'Descargar APK', 'description' => 'App móvil para rutas, escaneo y ventas en el teléfono.'],
        ] as $key => $meta)
            @php($app = $apps[$key] ?? null)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900">{{ $meta['label'] }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $meta['description'] }}</p>

                @if ($app)
                    <dl class="mt-4 space-y-1 text-sm text-slate-500">
                        @if ($app['version'])
                            <div class="flex justify-between gap-3">
                                <dt>Versión</dt>
                                <dd class="font-medium text-slate-700">{{ $app['version'] }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3">
                            <dt>Tamaño</dt>
                            <dd class="font-medium text-slate-700">{{ number_format($app['size'] / 1048576, 1) }} MB</dd>
                        </div>
                        @if ($app['updated_at'])
                            <div class="flex justify-between gap-3">
                                <dt>Actualizado</dt>
                                <dd class="font-medium text-slate-700">{{ date('d/m/Y H:i', $app['updated_at']) }}</dd>
                            </div>
                        @endif
                    </dl>

                    <a href="{{ route('descargas.download', $key) }}"
                       class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                        {{ $meta['cta'] }}
                    </a>
                    <p class="mt-2 truncate text-xs text-slate-400" title="{{ $app['filename'] }}">{{ $app['filename'] }}</p>
                @else
                    <p class="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">
                        Todavía no hay instalador publicado para esta plataforma.
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
        <p class="font-semibold text-slate-800">POS web vs. POSMoon Offline</p>
        <p class="mt-2">
            El punto de venta del navegador necesita internet. Para vender sin conexión, instalá
            <strong>POSMoon Offline</strong> en la PC de caja. La app Android es para rutas y ventas móviles.
        </p>
    </div>
@endsection
