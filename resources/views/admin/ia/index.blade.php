@extends('layouts.app')

@section('titulo', 'Admin IA (supermegaadmin)')

@section('contenido')
<div class="space-y-6">
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Solo vos como supermegaadmin ves esta pantalla: proveedor LLM (OpenAI / OpenRouter / Groq / custom), API key, cupos y aprobación de compras de créditos.
        Los clientes solo ven saldo y pueden solicitar paquetes desde el Asistente.
    </div>

    {{-- Proveedor --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-semibold text-slate-800">Proveedor LLM</h2>
        <p class="mb-4 text-xs text-slate-500">
            Fuente activa: <strong>{{ $resolver['fuente'] }}</strong>
            · modelo <code>{{ $resolver['model'] }}</code>
            · {{ $resolver['api_key'] !== '' ? 'clave cargada' : 'sin clave' }}
            (si no hay clave en DB, usa <code>OPENAI_*</code> del .env).
        </p>
        <form method="POST" action="{{ route('admin.ia.config') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor</label>
                <select name="provider" id="ia-provider" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($providers as $key => $p)
                        <option value="{{ $key }}" @selected($config->provider === $key)>{{ $p['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">API key</label>
                <input type="password" name="api_key" placeholder="{{ $config->api_key ? '******** (dejar vacío para no cambiar)' : 'sk-…' }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" autocomplete="off">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Modelo</label>
                <input type="text" name="model" value="{{ old('model', $config->model) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                       placeholder="gpt-4o-mini">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Base URL</label>
                <input type="text" name="base_url" value="{{ old('base_url', $config->base_url) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                       placeholder="https://api.openai.com/v1">
            </div>
            <div class="flex items-end gap-2 pb-1">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="activo" value="1" @checked($config->activo) class="rounded border-slate-300">
                    Activo (usar config DB)
                </label>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar proveedor</button>
            </div>
        </form>
    </section>

    {{-- Acreditar --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-semibold text-slate-800">Acreditar créditos / cupo por empresa</h2>
        <form method="POST" action="{{ route('admin.ia.acreditar') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div class="lg:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Empresa</label>
                <select name="empresa_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($empresas as $e)
                        <option value="{{ $e->id }}">
                            {{ $e->razon_social }}
                            — {{ $e->cupo_ia['restantes'] }} restantes
                            ({{ $e->cupo_ia['mensual'] }}/mes + {{ $e->cupo_ia['extras'] }} extra)
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Créditos a sumar *</label>
                <input type="number" name="creditos" min="1" value="100" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Override cupo mensual</label>
                <input type="number" name="ia_cupo_override" min="1" placeholder="opcional"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Plan</label>
                <select name="ia_plan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— no cambiar —</option>
                    <option value="incluido">Incluido</option>
                    <option value="abono">Abono</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Abono hasta</label>
                <input type="date" name="ia_abono_hasta" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Acreditar</button>
            </div>
        </form>
    </section>

    {{-- Compras pendientes --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-semibold text-slate-800">Solicitudes de compra</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-100 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-2 py-2">Fecha</th>
                        <th class="px-2 py-2">Empresa</th>
                        <th class="px-2 py-2">Paquete</th>
                        <th class="px-2 py-2">Créditos</th>
                        <th class="px-2 py-2">Precio</th>
                        <th class="px-2 py-2">Estado</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($compras as $c)
                        <tr class="border-b border-slate-50">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-2 py-2">{{ $c->empresa?->razon_social }}</td>
                            <td class="px-2 py-2">{{ $c->paquete?->nombre ?? '—' }}</td>
                            <td class="px-2 py-2">{{ $c->creditos }}</td>
                            <td class="px-2 py-2">${{ number_format((float) $c->precio, 0, ',', '.') }}</td>
                            <td class="px-2 py-2">
                                <span class="@class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-amber-50 text-amber-800' => $c->estado === 'solicitada',
                                    'bg-emerald-50 text-emerald-800' => $c->estado === 'aprobada',
                                    'bg-rose-50 text-rose-800' => $c->estado === 'rechazada',
                                ])">{{ $c->estado }}</span>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                @if ($c->estado === 'solicitada')
                                    <form method="POST" action="{{ route('admin.ia.compras.aprobar', $c) }}" class="inline">@csrf
                                        <button class="text-xs font-semibold text-emerald-700">Aprobar</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.ia.compras.rechazar', $c) }}" class="inline ml-2">@csrf
                                        <button class="text-xs font-semibold text-rose-700">Rechazar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-2 py-4 text-slate-500">Sin solicitudes aún.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Paquetes --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-semibold text-slate-800">Paquetes de créditos (estilo Gauchada)</h2>
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            @foreach ($paquetes as $p)
                <form method="POST" action="{{ route('admin.ia.paquetes') }}" class="rounded-lg border border-slate-100 p-3">
                    @csrf
                    <input type="hidden" name="id" value="{{ $p->id }}">
                    <input type="text" name="nombre" value="{{ $p->nombre }}" class="mb-2 w-full rounded border border-slate-300 px-2 py-1 text-sm font-semibold">
                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <input type="number" name="creditos" value="{{ $p->creditos }}" class="rounded border border-slate-300 px-2 py-1 text-sm" title="créditos">
                        <input type="number" step="0.01" name="precio" value="{{ $p->precio }}" class="rounded border border-slate-300 px-2 py-1 text-sm" title="precio">
                    </div>
                    <input type="hidden" name="moneda" value="{{ $p->moneda }}">
                    <input type="number" name="orden" value="{{ $p->orden }}" class="mb-2 w-20 rounded border border-slate-300 px-2 py-1 text-sm">
                    <label class="mb-2 flex items-center gap-2 text-xs"><input type="checkbox" name="activo" value="1" @checked($p->activo)> Activo</label>
                    <button class="text-xs font-semibold text-indigo-700">Guardar</button>
                </form>
            @endforeach
        </div>
        <details>
            <summary class="cursor-pointer text-sm font-medium text-indigo-700">+ Nuevo paquete</summary>
            <form method="POST" action="{{ route('admin.ia.paquetes') }}" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @csrf
                <input type="text" name="nombre" placeholder="Nombre" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="number" name="creditos" placeholder="Créditos" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="number" step="0.01" name="precio" placeholder="Precio" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="number" name="orden" value="10" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" checked> Activo</label>
                <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Crear</button>
            </form>
        </details>
    </section>

    {{-- Superadmins --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-semibold text-slate-800">Supermegaadmins</h2>
        <ul class="mb-4 list-disc pl-5 text-sm text-slate-700">
            @forelse ($superadmins as $u)
                <li>{{ $u->name }} &lt;{{ $u->email }}&gt;</li>
            @empty
                <li class="text-slate-500">Ninguno aún. Marcá uno abajo o definí <code>SUPERADMIN_EMAIL</code> en el .env y migrá.</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('admin.ia.superadmin') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email usuario</label>
                <input type="email" name="email" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="activo" value="1" checked> Otorgar flag</label>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium">Actualizar</button>
        </form>
    </section>
</div>
@endsection
