@extends('layouts.app')

@section('titulo', 'Roles y permisos')

@section('contenido')
    @php($etiquetas = [
        'pos' => 'Punto de venta', 'ventas' => 'Ventas', 'cajas' => 'Cajas',
        'clientes' => 'Clientes', 'proveedores' => 'Proveedores', 'productos' => 'Productos',
        'categorias' => 'Categorías', 'listas-precio' => 'Listas de precio', 'stock' => 'Stock',
        'usuarios' => 'Usuarios', 'sucursales' => 'Sucursales', 'medios-pago' => 'Medios de pago',
        'empresa' => 'Mi empresa', 'empresas' => 'Empresas (sistema)', 'facturacion' => 'Facturación',
        'emisores' => 'Emisores AFIP', 'informes' => 'Informes', 'retenciones' => 'Retenciones IIBB',
        'compras' => 'Compras', 'presupuestos' => 'Presupuestos', 'roles' => 'Roles',
        'cuentas-corrientes' => 'Cuentas corrientes',
    ])

    <form method="POST" action="{{ route('roles.store') }}"
          class="mb-4 flex items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <div class="flex-1">
            <label class="mb-1 block text-sm font-medium text-slate-700">Nuevo rol</label>
            <input type="text" name="name" value="{{ old('name') }}" required placeholder="Ej: Encargado de depósito"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Crear rol
        </button>
    </form>

    <div class="space-y-3">
        @foreach ($roles as $rol)
            <details class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer items-center justify-between px-5 py-4">
                    <div>
                        <p class="font-semibold">{{ $rol->name }}
                            @if ($rol->name === 'Administrador')
                                <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">Acceso total</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">{{ $rol->permissions->count() }} permisos · {{ $rol->users_count }} usuario(s)</p>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </summary>

                <div class="border-t border-slate-100 p-5">
                    @if ($rol->name === 'Administrador')
                        <p class="text-sm text-slate-500">El rol Administrador siempre tiene todos los permisos del sistema.</p>
                    @else
                        <form method="POST" action="{{ route('roles.update', $rol) }}">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($permisos as $modulo => $lista)
                                    <fieldset class="rounded-lg border border-slate-200 p-3">
                                        <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            {{ $etiquetas[$modulo] ?? ucfirst($modulo) }}
                                        </legend>
                                        <div class="space-y-1.5">
                                            @foreach ($lista as $permiso)
                                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                                    <input type="checkbox" name="permisos[]" value="{{ $permiso->name }}"
                                                           {{ $rol->hasPermissionTo($permiso->name) ? 'checked' : '' }}
                                                           class="rounded border-slate-300">
                                                    {{ str_replace(['.', '_', '-'], ' ', explode('.', $permiso->name)[1] ?? $permiso->name) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Guardar permisos
                                </button>
                                @if ($rol->users_count === 0)
                                    <button form="eliminar-rol-{{ $rol->id }}"
                                            onclick="return confirm('¿Eliminar el rol {{ $rol->name }}?')"
                                            class="text-sm font-medium text-red-500 hover:text-red-700">
                                        Eliminar rol
                                    </button>
                                @endif
                            </div>
                        </form>
                        <form id="eliminar-rol-{{ $rol->id }}" method="POST" action="{{ route('roles.destroy', $rol) }}">
                            @csrf @method('DELETE')
                        </form>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
@endsection
