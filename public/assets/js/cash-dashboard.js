(() => {
    const panel = document.getElementById('cash-dashboard-panel');
    const form = document.getElementById('cash-dashboard-filters');
    const status = document.getElementById('cash-dashboard-status');
    const button = document.getElementById('cash-dashboard-refresh');
    const auto = document.getElementById('cash-dashboard-auto');
    if (!panel || !form) return;
    // Keep the applied scope until the user submits the filters.
    const url = new URL(form.action, location.href);
    new FormData(form).forEach((value,key) => url.searchParams.set(key,value));
    url.searchParams.set('cash_dashboard','1');
    let busy = false;
    const refresh = async () => {
        if (busy) return;
        busy = true;
        if (button) button.disabled = true;
        if (status) status.textContent = 'Actualizando…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url, {cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'},signal:controller.signal});
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw Error();
            const data = await response.json();
            if (typeof data.html !== 'string') throw Error();
            const expanded = panel.querySelector('details')?.open;
            panel.innerHTML = data.html;
            if (expanded) panel.querySelector('details').open = true;
            if (status) status.textContent = 'Resumen actualizado';
        } catch (_) {
            if (status) status.textContent = 'No se pudo actualizar. Se conservan los últimos datos; vuelve a intentar.';
        }
        finally {
            clearTimeout(timeout);
            busy = false;
            if (button) button.disabled = false;
        }
    };
    if (button) button.addEventListener('click', refresh);
    if (auto) {
        setInterval(() => {
            if (auto.checked && !document.hidden && !document.querySelector('.modal.show, dialog[open]') && !panel.contains(document.activeElement)) refresh();
        }, 60000);
    }
})();
