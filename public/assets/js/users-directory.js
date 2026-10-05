(() => {
    const root = document.getElementById('users-directory');
    if (!root) return;
    const rows = [...root.querySelectorAll('[data-user-row]')];
    const search = document.getElementById('users-search');
    const role = document.getElementById('users-role');
    const state = document.getElementById('users-state');
    const company = document.getElementById('users-company');
    const size = document.getElementById('users-size');
    const form = document.getElementById('users-filters');
    const controls = [...root.querySelectorAll('[data-page]')];
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const terms = new Map(rows.map(row => [row, normalize(row.dataset.search)]));
    let page = 1, pages = 1;
    function render() {
        const query = normalize(search.value);
        const matches = rows.filter(row => terms.get(row).includes(query)
            && (!role.value || row.dataset.role === role.value)
            && (!state.value || row.dataset.active === state.value)
            && (!company || company.value === '*' || row.dataset.company === company.value));
        const limit = Number(size.value);
        pages = Math.max(1, Math.ceil(matches.length / limit));
        page = Math.min(page, pages);
        const start = (page - 1) * limit;
        const visible = new Set(matches.slice(start, start + limit));
        rows.forEach(row => { row.hidden = !visible.has(row); });
        document.getElementById('users-empty').hidden = matches.length > 0;
        document.getElementById('users-range').textContent = matches.length
            ? `${start + 1}–${Math.min(start + limit, matches.length)} de ${matches.length} usuarios${matches.length !== rows.length ? ` · ${rows.length} en total` : ''}`
            : 'Sin resultados';
        document.getElementById('users-page').textContent = `Página ${page} de ${pages}`;
        controls.forEach(button => { button.disabled = ['first', 'prev'].includes(button.dataset.page) ? page === 1 : page === pages; });
    }
    form.addEventListener('submit', event => event.preventDefault());
    search.addEventListener('input', () => { page = 1; render(); });
    [role, state, company, size].filter(Boolean).forEach(field => field.addEventListener('change', () => { page = 1; render(); }));
    form.addEventListener('reset', event => {
        event.preventDefault(); search.value = ''; role.value = ''; state.value = '';
        if (company) company.value = '*';
        page = 1; render(); search.focus();
    });
    controls.forEach(button => button.addEventListener('click', () => {
        page = button.dataset.page === 'first' ? 1 : button.dataset.page === 'last' ? pages : page + (button.dataset.page === 'next' ? 1 : -1);
        render();
    }));
    document.getElementById('users-pagination').hidden = false;
    render();
})();
