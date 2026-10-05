(() => {
    let panel = document.getElementById('seller-cash');
    if (!panel) return;
    const status = document.getElementById('seller-cash-status');
    let page = 1, busy = false, pending = false;
    function paginate() {
        const rows = [...panel.querySelectorAll('[data-cash-register]')];
        const pages = Math.max(1, Math.ceil(rows.length / 5));
        page = Math.min(page, pages);
        rows.forEach((row, index) => {
            const visible = index >= (page - 1) * 5 && index < page * 5;
            row.classList.toggle('d-none', !visible);
        });
        const nav = panel.querySelector('[data-cash-pagination]');
        nav.classList.toggle('d-none', rows.length <= 5);
        nav.hidden = rows.length <= 5;
        panel.querySelector('[data-cash-page-label]').textContent = `Página ${page} de ${pages}`;
        panel.querySelector('[data-cash-prev]').disabled = page === 1;
        panel.querySelector('[data-cash-next]').disabled = page === pages;
    }
    document.addEventListener('click', event => {
        if (event.target.closest('[data-cash-prev]')) { page--; paginate(); }
        if (event.target.closest('[data-cash-next]')) { page++; paginate(); }
    });
    async function refresh() {
        if (busy) { pending = true; return; }
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const url = new URL(location.href);
            url.searchParams.delete('cash_dashboard');
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            if (!response.ok || response.redirected) throw new Error();
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.getElementById('seller-cash');
            if (!next || next.dataset.company !== panel.dataset.company) throw new Error();
            panel.replaceWith(next);
            panel = next;
            paginate();
            window.initCodexPopups?.();
            status.textContent = '';
        } catch (_) {
            status.textContent = 'No se pudo actualizar la caja. Vuelve a cargar la pantalla para consultar su estado.';
        } finally {
            clearTimeout(timeout);
            busy = false;
            if (pending) { pending = false; refresh(); }
        }
    }
    window.addEventListener('codex:item-saved', refresh);
    window.addEventListener('codex:cash-saved', refresh);
    paginate();
})();
