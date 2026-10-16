(() => {
    'use strict';
    const form = document.getElementById('treasury-reconciliation');
    if (!form) return;
    const session = form.elements.namedItem('cash_session_id'), key = form.elements.namedItem('reconciliation_key'), currency = form.elements.namedItem('currency_code');
    const medium = document.getElementById('reconciliation-medium'), expected = document.getElementById('reconciliation-expected');
    const status = document.getElementById('reconciliation-status'), save = document.getElementById('reconciliation-save');
    let balances = [], generation = 0;
    function select() {
        const row = balances[Number(medium.value)];
        save.disabled = !row;
        key.value = row?.reconciliation_key || ''; currency.value = row?.currency_code || '';
        expected.value = row ? Number(row.total).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2}) : '';
    }
    async function refresh() {
        const current = ++generation, oldKey = key.value, oldCurrency = currency.value;
        save.disabled = true; medium.replaceChildren(); expected.value = ''; status.textContent = 'Consultando saldos…';
        const url = new URL(form.dataset.preview,window.location.href);
        url.searchParams.set('company_id',form.elements.namedItem('company_id').value);
        url.searchParams.set('cash_session_id',session.value);
        const abort = new AbortController(), timeout = setTimeout(() => abort.abort(),15000);
        try {
            const response = await fetch(url,{credentials:'same-origin',cache:'no-store',signal:abort.signal,headers:{Accept:'application/json'}});
            const result = await response.json();
            if (current !== generation) return;
            if (!response.ok || !Array.isArray(result.balances)) throw new Error(result.error || 'No se pudieron consultar los saldos.');
            balances = result.balances;
            balances.forEach((row,index) => {
                const option = document.createElement('option'); option.value = String(index); option.textContent = row.label;
                option.selected = row.reconciliation_key === oldKey && row.currency_code === oldCurrency;
                medium.append(option);
            });
            select(); status.textContent = 'El saldo se volverá a calcular al guardar. La conciliación no genera ajustes automáticos.';
        } catch (error) {
            if (current !== generation) return;
            balances = []; key.value = ''; currency.value = '';
            status.textContent = error.name === 'AbortError' ? 'La consulta tardó demasiado. Pulsa actualizar para reintentar.' : error.message;
        } finally { clearTimeout(timeout); }
    }
    session.addEventListener('change',refresh); medium.addEventListener('change',select);
    document.getElementById('reconciliation-refresh').addEventListener('click',refresh);
    refresh();
})();
