(() => {
    'use strict';
    const root = document.querySelector('#users-directory');
    if (!root) return;
    const $ = selector => root.querySelector(selector);
    const rows = Array.from(root.querySelectorAll('[data-ud-row]'));
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const records = rows.map(row => ({row, text: normalize(Array.from(row.cells).slice(0, 5).map(cell => cell.textContent).join(' '))}));
    let page = 1;
    function render() {
        const query = normalize($('#ud-search').value);
        const role = $('#ud-role').value, company = $('#ud-company').value, state = $('#ud-state').value;
        const matches = records.filter(({row, text}) => text.includes(query)
            && (role === '*' || row.dataset.role === role)
            && (company === '*' || row.dataset.company === company)
            && (state === '*' || row.dataset.state === state));
        const size = Number($('#ud-size').value);
        const pages = Math.max(1, Math.ceil(matches.length / size));
        page = Math.max(1, Math.min(page, pages));
        const start = (page - 1) * size;
        rows.forEach(row => { row.hidden = true; });
        matches.slice(start, start + size).forEach(({row}) => { row.hidden = false; });
        $('#ud-empty').hidden = matches.length > 0;
        $('#ud-summary').textContent = matches.length
            ? `${start + 1}–${Math.min(start + size, matches.length)} de ${matches.length} usuarios · ${rows.length} en total`
            : 'Sin resultados';
        $('#ud-page').textContent = `Página ${page} de ${pages}`;
        $('#ud-prev').disabled = page === 1;
        $('#ud-next').disabled = page === pages;
    }
    $('#ud-search').addEventListener('input', () => { page = 1; render(); });
    ['#ud-role', '#ud-company', '#ud-state', '#ud-size'].forEach(id => {
        $(id).addEventListener('change', () => { page = 1; render(); });
    });
    $('#ud-clear').addEventListener('click', () => {
        $('#ud-search').value = '';
        ['#ud-role', '#ud-company', '#ud-state'].forEach(id => { $(id).value = '*'; });
        page = 1; render(); $('#ud-search').focus();
    });
    $('#ud-prev').addEventListener('click', () => { page--; render(); });
    $('#ud-next').addEventListener('click', () => { page++; render(); });
    $('#ud-toolbar').hidden = false;
    $('#ud-pagination').hidden = false;
    render();
})();
