<form method="POST" action="{{ route('clientes.cuenta.registrar', $titular) }}"
      class="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
    @csrf
    <p class="text-sm font-semibold">Registrar movimiento</p>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <select name="tipo" required class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
            <option value="factura">Cargo / factura</option>
            <option value="pago">Pago del cliente</option>
            <option value="ajuste">Ajuste</option>
        </select>
        <input type="text" name="concepto" placeholder="Concepto" required
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2">
        <input type="number" step="0.01" min="0.01" name="importe" placeholder="Importe $" required
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label class="flex items-center gap-1.5 text-xs text-slate-600">
                <input type="checkbox" name="resta" value="1" class="rounded border-slate-300">
                Si es ajuste, resta deuda
            </label>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Registrar
        </button>
    </div>
</form>
