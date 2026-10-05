(() => {
    'use strict';
    const shell = Array.from(document.querySelectorAll('.purchases-shell')).find(element => !element.dataset.purchasesReady);
    if (!shell) return;
    shell.dataset.purchasesReady = 'true';
    const content = shell.querySelector('#purchases-content');
    const filter = shell.querySelector('#purchases-filters');
    const status = shell.querySelector('#purchases-status');
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
        content.querySelectorAll('[data-purchases-table]').forEach((table, key) => {
            table.querySelectorAll('.no-results-row,.no-data-row').forEach(row => row.remove());
            const totals = table.querySelectorAll('[data-purchases-total]');
            if (totals.length) {
                const foot = table.tFoot || table.createTFoot();
                totals.forEach(row => foot.append(row));
            }
            const rows = Array.from(table.tBodies[0]?.rows || []).filter(row => !row.querySelector('[colspan]'));
            const search = document.createElement('label');
            search.className = 'purchases-search';
            search.textContent = 'Buscar en este listado';
            const input = document.createElement('input');
            input.type = 'search'; input.className = 'form-control form-control-sm';
            input.value = searches.get(key) || ''; search.append(input);
            const filterSlot = table.closest('.card-body')?.querySelector('.purchases-list-filter');
            if (filterSlot) filterSlot.append(search); else table.before(search);
            const pager = document.createElement('nav');
            pager.className = 'purchases-pager'; pager.setAttribute('aria-label', 'Paginación del listado');
            const summary = document.createElement('span'); summary.setAttribute('aria-live', 'polite');
            const controls = document.createElement('div');
            const previous = document.createElement('button'); const next = document.createElement('button');
            [previous,next].forEach(button => {button.type = 'button'; button.className = 'btn btn-outline-secondary btn-sm';});
            previous.textContent = 'Anterior'; next.textContent = 'Siguiente';
            const position = document.createElement('span');
            controls.append(previous,position,next); pager.append(summary,controls); table.after(pager);
            function render() {
                const term = input.value.trim().toLocaleLowerCase('es');
                const globalTerm = content.querySelector('#purchasesSearchInput')?.value.trim().toLocaleLowerCase('es') || '';
                const matches = rows.filter(row => row.textContent.toLocaleLowerCase('es').includes(term) && row.textContent.toLocaleLowerCase('es').includes(globalTerm));
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
            content.querySelector('#purchasesSearchInput')?.addEventListener('input', () => {pages.set(key,1);render();});
            render();
        });
        const globalInput = content.querySelector('#purchasesSearchInput');
        const clear = content.querySelector('#clearPurchasesSearchBtn');
        if (globalInput && clear) {
            clear.style.display = globalInput.value ? '' : 'none';
            globalInput.addEventListener('input', () => {clear.style.display = globalInput.value ? '' : 'none';});
            clear.addEventListener('click', () => {globalInput.value = '';globalInput.dispatchEvent(new Event('input'));});
            content.querySelectorAll('.supplier-name-trigger').forEach(trigger => {
                trigger.tabIndex = 0; trigger.setAttribute('role','button');
                const select = () => {globalInput.value=trigger.dataset.supplier || '';globalInput.dispatchEvent(new Event('input'));};
                trigger.addEventListener('click',select);
                trigger.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();select();}});
            });
        }
    }
    initialize();
    // Entry forms stay editable; new lines receive the same responsive labels.
    content.querySelectorAll('tbody').forEach(body => new MutationObserver(() => labelRows(body.closest('table'))).observe(body, {childList:true}));
    if (!filter) return;
    let busy = false;
    let pendingRefresh = false;
    let saving = false;
    let dirty = false;
    filter.addEventListener('input', () => {dirty = true;});
    filter.addEventListener('change', () => {dirty = true;});
    filter.querySelector('[name=company_id]').addEventListener('change', () => {filter.querySelector('[name=supplier_id]').value = '';});
    async function refresh(manual = false, afterMutation = false) {
        if (afterMutation && (busy || saving)) {pendingRefresh = true; return;}
        const typing = shell.contains(document.activeElement) && document.activeElement.matches('input,select,textarea');
        if (busy || saving || (!manual && !afterMutation && (document.hidden || dirty || typing || document.querySelector('.modal.show,.popup-overlay.is-open')))) return;
        if (dirty && manual && !afterMutation) {filter.requestSubmit(); return;}
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(),15000);
        const button = shell.querySelector('[data-purchases-refresh]');
        button.disabled = true; content.setAttribute('aria-busy','true');
        try {
            const response = await fetch(window.location.href, {cache:'no-store', signal:controller.signal, credentials:'same-origin'});
            if (!response.ok || response.redirected) throw new Error('refresh');
            const html = new DOMParser().parseFromString(await response.text(),'text/html');
            const updated = html.querySelector(`#purchases-content`);
            if (!updated || html.querySelector('.purchases-shell')?.dataset.purchasesPage !== shell.dataset.purchasesPage) throw new Error('content');
            const globalSearch = content.querySelector('#purchasesSearchInput')?.value || '';
            content.replaceChildren(...Array.from(updated.childNodes));
            const updatedSearch = content.querySelector('#purchasesSearchInput');
            if (updatedSearch) updatedSearch.value = globalSearch;
            const supplierSelect = filter.querySelector('[name=supplier_id]');
            const updatedSuppliers = html.querySelector('#purchases-filters [name=supplier_id]');
            if (updatedSuppliers) {
                const selected = supplierSelect.value;
                supplierSelect.replaceChildren(...Array.from(updatedSuppliers.childNodes));
                supplierSelect.value = selected;
            }
            initialize();
            status.textContent = manual ? 'Resumen actualizado.' : '';
        } catch (_) {
            status.textContent = 'No se pudo actualizar. Se conservan los últimos datos; pulsa Actualizar para reintentar.';
        } finally {
            clearTimeout(timeout); busy = false; button.disabled = false; content.removeAttribute('aria-busy');
            if (pendingRefresh) {pendingRefresh = false; refresh(false, true);}
        }
    }
    window.addEventListener('codex:item-saved', () => refresh(false, true));
    // Deletion and approval use the same business actions, without navigating away.
    content.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('form[method="post"]') || event.defaultPrevented) return;
        event.preventDefault();
        if (saving || busy) {status.textContent = 'Espera a que termine la actualización e inténtalo nuevamente.'; return;}
        const payload = new FormData(form);
        saving = true;
        const buttons = Array.from(form.querySelectorAll('button'));
        buttons.forEach(button => {button.disabled = true;});
        try {
            const response = await fetch(form.action, {method:'POST', body:payload, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('No se pudo confirmar la operación. Verifica el listado antes de reintentar.');
            const result = await response.json();
            if (result.csrfName && result.csrfHash) {
                content.querySelectorAll('input[type=hidden]').forEach(input => {if (input.name === result.csrfName) input.value = result.csrfHash;});
            }
            if (!response.ok || !result.ok) throw new Error(result.message || 'No se pudo completar la operación.');
            window.showCodexToast?.(result.message, 'success');
            saving = false;
            await refresh(false, true);
        } catch (error) {
            status.textContent = error.message || 'No se pudo completar la operación.';
        } finally {
            saving = false;
            buttons.forEach(button => {button.disabled = false;});
            if (pendingRefresh) {pendingRefresh = false; refresh(false, true);}
        }
    });
    shell.querySelector('[data-purchases-refresh]').addEventListener('click', () => refresh(true));
    const interval = setInterval(() => {if (!shell.isConnected) clearInterval(interval); else refresh();},60000);
})();
