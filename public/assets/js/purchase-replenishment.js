/* Compras > Reposición inteligente: selección, totales, filtros e informe IA. */
(() => {
    const root = document.getElementById('replenishment');
    if (!root) return;

    const money = (v) => '$ ' + Math.round(v).toLocaleString('es-AR');
    const rows = [...root.querySelectorAll('.repl-row')];
    let activeFilter = '';

    // Análisis: feedback mientras el servidor calcula.
    const params = document.getElementById('repl-params');
    params?.addEventListener('submit', () => {
        const btn = params.querySelector('.repl-analyze');
        btn.setAttribute('aria-busy', 'true');
        btn.disabled = true;
    });

    // Totales de la selección.
    const recalc = () => {
        let count = 0, total = 0;
        const suppliers = new Set();
        rows.forEach((row) => {
            const qty = parseFloat(row.querySelector('.repl-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.repl-cost').value) || 0;
            const supplier = row.querySelector('.repl-supplier').value;
            const sub = qty * cost;
            row.querySelector('.repl-subtotal').textContent = sub > 0 ? money(sub) : '—';
            row.querySelector('.repl-supplier').classList.toggle('is-invalid', row.querySelector('.repl-check').checked && !supplier);
            if (row.querySelector('.repl-check').checked && qty > 0) {
                count++;
                total += sub;
                if (supplier) suppliers.add(supplier);
            }
        });
        const set = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
        set('repl-selected-count', count);
        set('repl-selected-total', money(total));
        set('repl-selected-suppliers', suppliers.size);
        const create = document.getElementById('repl-create-orders');
        if (create) create.disabled = suppliers.size === 0;
    };
    root.addEventListener('input', (e) => { if (e.target.closest('.repl-row')) recalc(); });
    root.addEventListener('change', (e) => { if (e.target.closest('.repl-row')) recalc(); });

    // Filtros por prioridad y búsqueda.
    const search = document.getElementById('repl-search');
    const applyFilters = () => {
        const term = (search?.value || '').trim().toLowerCase();
        rows.forEach((row) => {
            const byPriority = !activeFilter
                || (activeFilter === 'sin-proveedor' ? !row.querySelector('.repl-supplier').value : row.dataset.priority === activeFilter);
            row.hidden = !(byPriority && (!term || row.dataset.search.includes(term)));
        });
    };
    root.querySelectorAll('[data-repl-filter]').forEach((btn) => btn.addEventListener('click', () => {
        root.querySelectorAll('[data-repl-filter]').forEach((b) => b.classList.toggle('active', b === btn));
        activeFilter = btn.dataset.replFilter;
        applyFilters();
    }));
    search?.addEventListener('input', applyFilters);

    document.getElementById('repl-select-all')?.addEventListener('change', (e) => {
        rows.filter((row) => !row.hidden).forEach((row) => { row.querySelector('.repl-check').checked = e.target.checked; });
        recalc();
    });

    // Solo se envían las líneas seleccionadas (evita superar max_input_vars con catálogos grandes).
    document.getElementById('repl-orders-form')?.addEventListener('submit', (e) => {
        rows.forEach((row) => {
            if (!row.querySelector('.repl-check').checked) {
                row.querySelectorAll('input:not(.repl-check), select').forEach((el) => { el.disabled = true; });
            }
        });
        if (e.defaultPrevented) rows.forEach((row) => row.querySelectorAll('input, select').forEach((el) => { el.disabled = false; }));
    });
    window.addEventListener('pageshow', () => rows.forEach((row) => row.querySelectorAll('input, select').forEach((el) => { el.disabled = false; })));

    // Informe ejecutivo con IA.
    const aiBtn = document.getElementById('repl-ai-btn');
    const aiOut = document.getElementById('repl-ai-output');
    const aiMeta = document.getElementById('repl-ai-meta');
    aiBtn?.addEventListener('click', async () => {
        const body = new FormData();
        body.append(root.dataset.csrfName, root.dataset.csrfHash);
        body.append('refresh', aiBtn.dataset.hasSummary === '1' ? '1' : '0');
        aiBtn.disabled = true;
        aiOut.hidden = false;
        aiOut.classList.add('is-loading');
        const previous = aiOut.textContent;
        aiOut.textContent = 'Analizando los resultados con el modelo de IA… puede demorar hasta un minuto.';
        try {
            const response = await fetch(root.dataset.aiUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (data.csrfHash) root.dataset.csrfHash = data.csrfHash;
            if (!response.ok) throw new Error(data.error || 'No se pudo generar el informe.');
            aiOut.textContent = data.summary;
            aiMeta.textContent = 'Generado ' + new Date(data.generated_at.replace(' ', 'T')).toLocaleString('es-AR') + ' · ' + data.provider
                + (data.provider === 'reglas' ? ' (el modelo de IA no respondió; se muestran los hallazgos del motor)' : '');
            aiBtn.dataset.hasSummary = '1';
            aiBtn.querySelector('span').textContent = 'Regenerar';
        } catch (error) {
            aiOut.textContent = previous || '';
            aiOut.hidden = !previous;
            aiMeta.textContent = error.message;
        } finally {
            aiOut.classList.remove('is-loading');
            aiBtn.disabled = false;
        }
    });

    recalc();
})();
