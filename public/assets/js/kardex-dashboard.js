(() => {
    const panel = document.getElementById('kardex-dashboard-panel');
    const form = document.getElementById('kardex-filters');
    const status = document.getElementById('kardex-refresh-status');
    if (!panel || !form) return;
    const applied = new URLSearchParams(new FormData(form)).toString();
    const url = new URL(form.action, location.href);
    url.search = applied;
    url.searchParams.set('summary_refresh', '1');
    let busy = false;
    const refresh = async () => {
        if (busy || document.hidden || document.querySelector('.modal.show, dialog[open]') || panel.contains(document.activeElement) || applied !== new URLSearchParams(new FormData(form)).toString()) return;
        busy = true;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url, {cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest'}, signal:controller.signal});
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw Error();
            const data = await response.json();
            if (typeof data.html !== 'string') throw Error();
            const expanded = panel.querySelector('details')?.open;
            panel.innerHTML = data.html;
            if (expanded && panel.querySelector('details')) panel.querySelector('details').open = true;
            status.textContent = '';
        } catch (_) {
            status.textContent = 'No se pudo actualizar el resumen. Se conservan los datos anteriores.';
        } finally { clearTimeout(timer); busy = false; }
    };
    setInterval(refresh, 60000);
})();
