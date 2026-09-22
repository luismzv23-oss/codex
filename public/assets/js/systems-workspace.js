(() => {
    'use strict';
    const root = document.querySelector('#systems-workspace');
    if (!root) return;
    const $ = selector => root.querySelector(selector);
    const cards = Array.from(root.querySelectorAll('[data-sw-card]'));
    if (!cards.length) return;
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const records = cards.map(card => ({card, text: normalize(card.querySelector('h3').textContent + ' ' + card.querySelector('p').textContent)}));
    function filter() {
        const query = normalize($('#sw-search').value), access = $('#sw-access').value;
        let count = 0;
        records.forEach(({card, text}) => {
            card.hidden = !text.includes(query) || (access !== '*' && card.dataset.access !== access);
            if (!card.hidden) count++;
        });
        $('#sw-empty').hidden = count > 0;
        $('#sw-results').textContent = `${count} de ${cards.length} módulos`;
    }
    $('#sw-search').addEventListener('input', filter);
    $('#sw-access').addEventListener('change', filter);
    $('#sw-clear').addEventListener('click', () => {
        $('#sw-search').value = ''; $('#sw-access').value = '*'; filter(); $('#sw-search').focus();
    });
    $('#sw-toolbar').hidden = false;
    $('#sw-results').hidden = false;
    filter();
})();
