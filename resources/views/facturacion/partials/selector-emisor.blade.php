{{--
    Selector de emisor + punto de venta (Alpine: emisorId, puntoVentaId, onEmisorChange opcional).
    Variables: $emisores (Collection)
--}}
<label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Facturar como (CUIT emisor)</label>
<select x-model="emisorId" @change="onEmisorChange && onEmisorChange()"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
    @foreach ($emisores as $emisor)
        <option value="{{ $emisor->id }}">{{ $emisor->razon_social }} · {{ $emisor->cuit }}
            ({{ $emisor->condicion_iva === 'MONOTRIBUTO' ? 'Factura C' : 'A/B' }} · {{ $emisor->entorno }})
        </option>
    @endforeach
</select>
<input type="hidden" name="emisor_id" :value="emisorId">

<label class="mb-1 mt-2 block text-xs font-semibold uppercase tracking-wider text-slate-500">Punto de venta</label>
@foreach ($emisores as $emisor)
    <select x-model="puntoVentaId" x-show="Number(emisorId) === {{ $emisor->id }}"
            :disabled="Number(emisorId) !== {{ $emisor->id }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        @forelse ($emisor->puntosVenta->where('activo', true) as $pv)
            <option value="{{ $pv->id }}">PV {{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }} — {{ $pv->descripcion }}</option>
        @empty
            <option value="">Sin puntos de venta activos</option>
        @endforelse
    </select>
@endforeach
<input type="hidden" name="punto_venta_id" :value="puntoVentaId">
