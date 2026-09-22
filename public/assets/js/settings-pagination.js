(() => {
    'use strict';
    document.querySelectorAll('#settings-workspace [data-st-pagination]').forEach(table => {
        const body = table;
        if (!body) return;
        const rows = Array.from(body.children).filter(row => !row.hasAttribute('data-st-empty'));
        const size = Number(table.dataset.stPagination) || 5;
        const pages = Math.max(1, Math.ceil(rows.length / size));
        let page = 1;
        const nav = document.createElement('nav');
        nav.className = 'st-pagination';
        nav.setAttribute('aria-label', 'Paginación: ' + table.getAttribute('aria-label'));
        const summary = document.createElement('span');
        summary.setAttribute('role', 'status');
        summary.setAttribute('aria-live', 'polite');
        const controls = document.createElement('div');
        const indicator = document.createElement('span');
        function button(label, icon, target) {
            const element = document.createElement('button');
            element.type = 'button';
            element.title = label;
            element.setAttribute('aria-label', label);
            element.setAttribute('aria-controls', table.id);
            element.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i>`;
            element.addEventListener('click', () => {
                page = Math.max(1, Math.min(pages, target()));
                render();
            });
            return element;
        }
        const first = button('Primera página', 'bi-chevron-double-left', () => 1);
        const prev = button('Página anterior', 'bi-chevron-left', () => page - 1);
        const next = button('Página siguiente', 'bi-chevron-right', () => page + 1);
        const last = button('Última página', 'bi-chevron-double-right', () => pages);
        controls.append(first, prev, indicator, next, last);
        nav.append(summary, controls);
        table.after(nav);
        function render() {
            const start = (page - 1) * size;
            rows.forEach((row, index) => { row.hidden = index < start || index >= start + size; });
            summary.textContent = rows.length ? `${start + 1}–${Math.min(start + size, rows.length)} de ${rows.length} registros` : 'Sin registros';
            indicator.textContent = `Página ${page} de ${pages}`;
            first.disabled = prev.disabled = page === 1;
            last.disabled = next.disabled = page === pages;
        }
        render();
    });
})();
