@extends('layouts.app')

@section('titulo', 'CITI ventas')

@section('contenido')
    @include('informes._nav')

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Export CITI ventas</h1>
            <p class="text-sm text-slate-500">Comprobantes autorizados del período (formato simplificado CSV/TXT).</p>
        </div>
    </div>

    <form method="get" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm">
                <span class="mb-1 block text-slate-500">Desde</span>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-slate-500">Hasta</span>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <button type="submit" name="formato" value="csv" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Descargar CSV
            </button>
            <button type="submit" name="formato" value="txt" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Descargar TXT
            </button>
        </div>
    </form>
@endsection
