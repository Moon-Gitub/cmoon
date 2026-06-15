(() => {
    const $ = id => document.getElementById(id);
    const fmt = window.cmoonCommon.fmt;
    let items = [], actual = null;
    let drawing = false, ctx;

    async function init() {
        if (! await window.cmoon.isActivated()) return window.location.href = 'setup.html';
        if (! (await window.cmoon.getConfig())?.can_entregas) return window.location.href = 'home.html';
        ctx = $('firma').getContext('2d');
        ctx.strokeStyle = '#111'; ctx.lineWidth = 2;
        bindFirma();
        $('btn-limpiar-firma').addEventListener('click', limpiarFirma);
        $('btn-cancelar').addEventListener('click', () => { $('modal').hidden = true; actual = null; });
        $('btn-confirmar').addEventListener('click', confirmar);
        window.cmoonCommon.bindTopbar(tick);
        await cargar();
    }

    function bindFirma() {
        const c = $('firma');
        const start = e => { drawing = true; ctx.beginPath(); const p = pos(e); ctx.moveTo(p.x, p.y); };
        const move = e => { if (! drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); };
        const end = () => { drawing = false; };
        c.addEventListener('mousedown', start); c.addEventListener('mousemove', move); c.addEventListener('mouseup', end);
        c.addEventListener('touchstart', e => { e.preventDefault(); start(e.touches[0]); });
        c.addEventListener('touchmove', e => { e.preventDefault(); move(e.touches[0]); });
        c.addEventListener('touchend', end);
    }

    function pos(e) {
        const r = $('firma').getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function limpiarFirma() { ctx.clearRect(0, 0, $('firma').width, $('firma').height); }

    async function cargar() {
        try {
            const data = navigator.onLine ? await window.cmoon.fetchEntregasPendientes() : await window.cmoon.getEntregasCache();
            items = data.items || [];
        } catch { items = (await window.cmoon.getEntregasCache()).items || []; }
        render();
    }

    function render() {
        $('lista').innerHTML = items.map((it, i) => `
            <button type="button" class="list-card" data-i="${i}">
                <strong>${it.tipo === 'presupuesto' ? 'Pedido' : 'Venta'} #${String(it.numero).padStart(6,'0')}</strong>
                <span class="muted">${it.cliente_nombre || ''}</span>
                <span>${fmt(it.total)}</span>
            </button>`).join('') || '<p class="muted">No hay entregas pendientes.</p>';
        $('lista').querySelectorAll('.list-card').forEach(b => b.addEventListener('click', () => abrir(Number(b.dataset.i))));
    }

    function abrir(i) {
        actual = items[i];
        $('m-titulo').textContent = `${actual.tipo === 'presupuesto' ? 'Pedido' : 'Venta'} #${String(actual.numero).padStart(6,'0')}`;
        $('m-cliente').textContent = actual.cliente_nombre || '';
        $('obs').value = '';
        limpiarFirma();
        $('foto').value = '';
        $('modal').hidden = false;
    }

    function firmaBase64() { return $('firma').toDataURL('image/png'); }

    function leerFoto() {
        return new Promise(resolve => {
            const f = $('foto').files?.[0];
            if (! f) return resolve([]);
            const r = new FileReader();
            r.onload = () => resolve([r.result]);
            r.readAsDataURL(f);
        });
    }

    async function confirmar() {
        if (! actual) return;
        const fotos = await leerFoto();
        const payload = {
            uuid: crypto.randomUUID(),
            cliente_id: actual.cliente_id,
            presupuesto_id: actual.presupuesto_id,
            venta_id: actual.venta_id,
            estado: 'entregada',
            observaciones: $('obs').value.trim() || null,
            firma_base64: firmaBase64(),
            fotos_base64: fotos,
        };
        try {
            await window.cmoon.submitEntrega(payload);
            alert('Entrega registrada');
            $('modal').hidden = true;
            items = items.filter(x => x !== actual);
            render();
            tick();
        } catch (e) { alert(e.message); }
    }

    async function tick() {
        await window.cmoonCommon.refreshStatus(async () => { if (navigator.onLine) await cargar(); });
    }
    init().catch(e => alert(e.message));
})();
