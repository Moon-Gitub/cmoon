@extends('layouts.app')

@section('titulo', 'Importar productos')

@section('contenido')
    <div class="max-w-2xl space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
            <p class="mb-2 font-semibold text-slate-800">Cómo funciona</p>
            <ul class="list-inside list-disc space-y-1">
                <li>Subí un archivo <strong>CSV</strong> (desde Excel: "Guardar como → CSV").</li>
                <li>Columnas: <code class="rounded bg-slate-100 px-1">codigo;nombre;precio_venta;precio_compra;iva;categoria;unidad;stock;stock_minimo</code></li>
                <li>Obligatorias: <strong>codigo, nombre, precio_venta</strong>. El resto es opcional.</li>
                <li>Si el código ya existe, el producto se <strong>actualiza</strong>; si no, se crea.</li>
                <li>Las categorías que no existan se crean solas.</li>
                <li>Si incluís la columna <code class="rounded bg-slate-100 px-1">stock</code>, se ajusta el stock en la sucursal principal.</li>
            </ul>
            <a href="{{ route('productos.plantilla') }}"
               class="mt-3 inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Descargar plantilla CSV
            </a>
        </div>

        <form method="POST" action="{{ route('productos.importar.procesar') }}" enctype="multipart/form-data"
              class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <label class="mb-1 block text-sm font-medium text-slate-700">Archivo CSV *</label>
            <input type="file" name="archivo" accept=".csv,.txt" required class="block w-full text-sm">
            @error('archivo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="mt-4 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                Importar
            </button>
        </form>

        <a href="{{ route('productos.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a productos</a>
    </div>
@endsection
