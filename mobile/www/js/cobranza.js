(() => {
    const $ = id => document.getElementById(id);
    const fmt = window.cmoonCommon.fmt;
    let clientes = [];

    async function init() {
        if (! await window.cmoon.isActivated()) return window.location.href = 'setup.html';
        if (! (await window.cmoon.getConfig())?.can_cobranzas) return window.location.href = 'home.html';
        clientes = (await window.cmoon.getCatalog()).clientes || [];
        $('cliente').innerHTML = clientes.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
        const pre = new URLSearchParams(location.search).get('cliente');
        if (pre) $('cliente').value = pre;
        $('cliente').addEventListener('change', updSaldo);
        updSaldo();
        $('btn-guardar').addEventListener('click', guardar);
        window.cmoonCommon.bindTopbar(tick);
        tick();
    }

    function updSaldo() {
        const c = clientes.find(x => Number(x.id) === Number($('cliente').value));
        $('saldo').textContent = c ? `Saldo actual: ${fmt(c.saldo || 0)}` : '';
    }

    async function guardar() {
        const importe = parseFloat($('importe').value);
        if (! importe || importe <= 0) return alert('Importe inválido');
        const payload = {
            uuid: crypto.randomUUID(),
            cliente_id: parseInt($('cliente').value, 10),
            importe,
            concepto: $('concepto').value.trim() || 'Cobranza móvil',
            fecha: new Date().toISOString().slice(0, 10),
        };
        try {
            const r = await window.cmoon.submitCobranza(payload);
            alert(r.online ? 'Cobranza registrada' : 'Guardada offline para sincronizar');
            $('importe').value = '';
            tick();
        } catch (e) { alert(e.message); }
    }

    async function tick() { await window.cmoonCommon.refreshStatus(); }
    init().catch(e => alert(e.message));
})();
