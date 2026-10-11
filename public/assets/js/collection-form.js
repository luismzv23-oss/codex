(() => {
    'use strict';
    document.querySelectorAll('[data-collection-items]').forEach(host => {
        const form = host.closest('form');
        const customer = form.querySelector('[data-collection-customer]');
        const kind = form.querySelector('[data-receipt-kind]');
        const search = host.querySelector('[data-collection-search]');
        const rows = [...host.querySelectorAll('[data-collection-row]')];
        let page = 1;
        function render() {
            const advance = kind?.value === 'advance';
            host.hidden = advance;
            const query = (search?.value || '').trim().toLocaleLowerCase();
            const matches = rows.filter(row => (!customer || row.dataset.customer === customer.value) && row.textContent.toLocaleLowerCase().includes(query));
            const pages = Math.max(1, Math.ceil(matches.length / 5));
            page = Math.max(1, Math.min(page, pages));
            rows.forEach(row => { row.hidden = true; });
            matches.slice((page - 1) * 5, page * 5).forEach(row => { row.hidden = false; });
            host.querySelector('[data-collection-page]').textContent = `${page} / ${pages} · ${matches.length} comprobantes`;
            host.querySelector('[data-collection-prev]').disabled = page === 1;
            host.querySelector('[data-collection-next]').disabled = page === pages;
        }
        [kind, customer].forEach(control => control?.addEventListener('change', () => {
            rows.forEach(row => { row.querySelector('[name="items_applied_amount[]"]').value = '0.00'; });
            page = 1; render();
        }));
        search?.addEventListener('input', () => { page = 1; render(); });
        host.querySelector('[data-collection-prev]').addEventListener('click', () => { page--; render(); });
        host.querySelector('[data-collection-next]').addEventListener('click', () => { page++; render(); });
        render();
    });
})();
