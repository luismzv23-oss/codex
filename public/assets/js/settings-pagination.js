(() => {
    'use strict';

    document.querySelectorAll('[data-settings-pagination]').forEach(list => {
        const rows = Array.from(list.children).filter(row => !row.hasAttribute('data-pagination-empty'));
        const pageSize = Number(list.dataset.settingsPagination) || 4;
        const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
        let page = 1;

        const nav = document.createElement('nav');
        nav.className = 'settings-pagination d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 mt-3';
        nav.setAttribute('aria-label', `Paginación: ${list.getAttribute('aria-label')}`);
        const summary = document.createElement('span');
        summary.className = 'small text-secondary';
        summary.setAttribute('role', 'status');
        summary.setAttribute('aria-live', 'polite');
        const controls = document.createElement('div');
        controls.className = 'd-flex align-items-center gap-2';
        const indicator = document.createElement('span');
        indicator.className = 'small text-secondary';

        function button(label, icon, target) {
            const element = document.createElement('button');
            element.type = 'button';
            element.className = 'btn btn-sm btn-outline-dark icon-btn';
            element.title = label;
            element.setAttribute('aria-label', label);
            element.setAttribute('aria-controls', list.id);
            const glyph = document.createElement('i');
            glyph.className = `bi ${icon}`;
            glyph.setAttribute('aria-hidden', 'true');
            element.append(glyph);
            element.addEventListener('click', () => {
                page = Math.max(1, Math.min(pageCount, target()));
                render();
            });
            return element;
        }

        const first = button('Primera página', 'bi-chevron-double-left', () => 1);
        const previous = button('Página anterior', 'bi-chevron-left', () => page - 1);
        const next = button('Página siguiente', 'bi-chevron-right', () => page + 1);
        const last = button('Última página', 'bi-chevron-double-right', () => pageCount);
        controls.append(first, previous, indicator, next, last);
        nav.append(summary, controls);
        list.after(nav);

        function render() {
            const start = (page - 1) * pageSize;
            rows.forEach((row, index) => { row.hidden = index < start || index >= start + pageSize; });
            summary.textContent = rows.length
                ? `${start + 1}–${Math.min(start + pageSize, rows.length)} de ${rows.length} registros`
                : 'Sin registros';
            indicator.textContent = `Página ${page} de ${pageCount}`;
            first.disabled = previous.disabled = page === 1;
            next.disabled = last.disabled = page === pageCount;
        }

        render();
    });
})();
