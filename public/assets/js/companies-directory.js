(() => {
    'use strict';
    const root = document.querySelector('#companies-directory');
    if (!root) return;
    const $ = selector => root.querySelector(selector);
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const rows = Array.from(root.querySelectorAll('[data-cd-row]'));
    const records = rows.map(row => ({row, text: normalize(Array.from(row.cells).slice(0, 3).map(cell => cell.textContent).join(' '))}));
    let page = 1;
    function render() {
        const query = normalize($('#cd-search').value), state = $('#cd-state').value;
        const matches = records.filter(({row, text}) => text.includes(query) && (state === '*' || row.dataset.state === state));
        const size = Number($('#cd-size').value);
        const pages = Math.max(1, Math.ceil(matches.length / size));
        page = Math.max(1, Math.min(page, pages));
        const start = (page - 1) * size;
        rows.forEach(row => { row.hidden = true; });
        matches.slice(start, start + size).forEach(({row}) => { row.hidden = false; });
        $('#cd-empty').hidden = matches.length > 0;
        $('#cd-summary').textContent = matches.length ? `${start + 1}–${Math.min(start + size, matches.length)} de ${matches.length} empresas` : 'Sin resultados';
        $('#cd-page').textContent = `Página ${page} de ${pages}`;
        $('#cd-prev').disabled = page === 1;
        $('#cd-next').disabled = page === pages;
    }
    $('#cd-search').addEventListener('input', () => { page = 1; render(); });
    ['#cd-state', '#cd-size'].forEach(id => $(id).addEventListener('change', () => { page = 1; render(); }));
    $('#cd-clear').addEventListener('click', () => {
        $('#cd-search').value = ''; $('#cd-state').value = '*'; page = 1; render(); $('#cd-search').focus();
    });
    $('#cd-prev').addEventListener('click', () => { page--; render(); });
    $('#cd-next').addEventListener('click', () => { page++; render(); });
    $('#cd-toolbar').hidden = false;
    $('#cd-pagination').hidden = false;
    render();
})();
