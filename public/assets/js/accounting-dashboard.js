(() => {
    'use strict';
    const shell = Array.from(document.querySelectorAll('.accounting-shell')).find(element => !element.dataset.accountingReady);
    if (!shell) return;
    shell.dataset.accountingReady = 'true';
    const content = shell.querySelector('#accounting-content');
    const filter = shell.querySelector('#accounting-filters');
    const status = shell.querySelector('#accounting-status');
    const pages = new Map();
    const searches = new Map();
    function labelRows(table) {
        const headers = Array.from(table.querySelectorAll('thead th'), th => th.textContent.trim());
        table.querySelectorAll('tbody tr,tfoot tr').forEach(row => {
            let column = 0;
            Array.from(row.cells).forEach(cell => {
                cell.dataset.label = cell.colSpan > 1 ? '' : (headers[column] || 'Acciones');
                column += cell.colSpan;
            });
        });
    }
    function initialize() {
        content.querySelectorAll('table').forEach(labelRows);
        content.querySelectorAll('[data-accounting-table]').forEach((table, key) => {
            const totals = table.querySelectorAll('[data-accounting-total]');
            if (totals.length) {
                const foot = table.tFoot || table.createTFoot();
                totals.forEach(row => foot.append(row));
            }
            const rows = Array.from(table.tBodies[0]?.rows || []).filter(row => !row.querySelector('[colspan]'));
            const search = document.createElement('label');
            search.className = 'accounting-search';
            search.textContent = 'Buscar en este listado';
            const input = document.createElement('input');
            input.type = 'search'; input.className = 'form-control form-control-sm';
            input.value = searches.get(key) || ''; search.append(input);
            table.before(search);
            const pager = document.createElement('nav');
            pager.className = 'accounting-pager'; pager.setAttribute('aria-label', 'Paginación del listado');
            const summary = document.createElement('span'); summary.setAttribute('aria-live', 'polite');
            const controls = document.createElement('div');
            const previous = document.createElement('button'); const next = document.createElement('button');
            [previous,next].forEach(button => {button.type = 'button'; button.className = 'btn btn-outline-secondary btn-sm';});
            previous.textContent = 'Anterior'; next.textContent = 'Siguiente';
            const position = document.createElement('span');
            controls.append(previous,position,next); pager.append(summary,controls); table.after(pager);
            function render() {
                const term = input.value.trim().toLocaleLowerCase('es');
                const matches = rows.filter(row => row.textContent.toLocaleLowerCase('es').includes(term));
                const count = Math.max(1, Math.ceil(matches.length / 5));
                const page = Math.min(pages.get(key) || 1, count); pages.set(key,page);
                rows.forEach(row => { row.hidden = true; });
                matches.slice((page-1)*5,page*5).forEach(row => { row.hidden = false; });
                summary.textContent = matches.length ? `${(page-1)*5+1}–${Math.min(page*5,matches.length)} de ${matches.length} registros` : 'Sin registros para mostrar';
                position.textContent = `Página ${page} de ${count}`;
                previous.disabled = page === 1; next.disabled = page === count;
            }
            previous.addEventListener('click', () => {pages.set(key,(pages.get(key)||1)-1); render();});
            next.addEventListener('click', () => {pages.set(key,(pages.get(key)||1)+1); render();});
            input.addEventListener('input', () => {searches.set(key,input.value); pages.set(key,1); render();});
            render();
        });
    }
    initialize();
    // Entry forms stay editable; new lines receive the same responsive labels.
    const lines = content.querySelector('#linesBody');
    if (lines) new MutationObserver(() => labelRows(lines.closest('table'))).observe(lines, {childList:true});
    if (!filter) return;
    let busy = false;
    let dirty = false;
    filter.addEventListener('input', () => {dirty = true;});
    filter.addEventListener('change', () => {dirty = true;});
    async function refresh(manual = false) {
        const typing = shell.contains(document.activeElement) && document.activeElement.matches('input,select,textarea');
        if (busy || (!manual && (document.hidden || dirty || typing || document.querySelector('.modal.show')))) return;
        if (dirty && manual) {filter.requestSubmit(); return;}
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(),15000);
        const button = shell.querySelector('[data-accounting-refresh]');
        button.disabled = true; content.setAttribute('aria-busy','true');
        try {
            const response = await fetch(window.location.href, {cache:'no-store', signal:controller.signal, credentials:'same-origin'});
            if (!response.ok || response.redirected) throw new Error('refresh');
            const html = new DOMParser().parseFromString(await response.text(),'text/html');
            const updated = html.querySelector(`#accounting-content`);
            if (!updated || html.querySelector('.accounting-shell')?.dataset.accountingPage !== shell.dataset.accountingPage) throw new Error('content');
            content.replaceChildren(...Array.from(updated.childNodes));
            initialize();
            status.textContent = manual ? 'Resumen actualizado.' : '';
        } catch (_) {
            status.textContent = 'No se pudo actualizar. Se conservan los últimos datos; pulsa Actualizar para reintentar.';
        } finally {clearTimeout(timeout); busy = false; button.disabled = false; content.removeAttribute('aria-busy');}
    }
    shell.querySelector('[data-accounting-refresh]').addEventListener('click', () => refresh(true));
    const interval = setInterval(() => {if (!shell.isConnected) clearInterval(interval); else refresh();},60000);
})();
