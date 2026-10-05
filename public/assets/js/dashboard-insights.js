(() => {
    const form = document.getElementById('insight-filters');
    if (!form) return;
    const content = document.getElementById('insight-content');
    const notice = document.getElementById('insight-notice');
    const updated = document.getElementById('insight-updated');
    const pause = document.getElementById('insight-pause');
    const period = document.getElementById('insight-period');
    let paused = false, request = null, generation = 0;
    let applied = new URLSearchParams(new FormData(form));
    const day = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
    period.value = form.elements.from.value === day(new Date(new Date().getFullYear(), new Date().getMonth(), 1)) && form.elements.to.value === day(new Date()) ? 'month' : 'custom';
    async function refresh(changeFilters = false) {
        if (changeFilters && !form.reportValidity()) return;
        const params = changeFilters ? new URLSearchParams(new FormData(form)) : new URLSearchParams(applied);
        const version = ++generation;
        request?.abort(); request = new AbortController();
        content.setAttribute('aria-busy', 'true');
        const url = new URL(form.action); url.search = params.toString();
        try {
            const response = await fetch(url, {cache:'no-store', signal:request.signal, headers:{'X-Requested-With':'XMLHttpRequest'}});
            if (response.redirected) throw Error('La sesión cambió. Recarga la página para continuar.');
            const data = await response.json();
            if (!response.ok || typeof data.html !== 'string') throw Error(data.message || 'No se pudo actualizar el dashboard.');
            if (version !== generation) return;
            const open = [...content.querySelectorAll('details[open]')].map(node => node.id);
            content.innerHTML = data.html;
            open.forEach(id => { const node = document.getElementById(id); if (node) node.open = true; });
            applied = params;
            updated.textContent = `Última lectura ${data.updated}${paused ? ' · pausada' : ''}`;
            notice.hidden = true;
            if (changeFilters) history.replaceState(null, '', url);
            const footer = document.querySelector('.insight-footer span');
            footer.textContent = `Datos de operaciones registradas · Importes en ${params.get('currency')}, sin conversión`;
        } catch (error) {
            if (error.name === 'AbortError' || version !== generation) return;
            notice.textContent = `${error.message} Se conserva la última lectura; los datos no están actualizados.`;
            notice.hidden = false;
        } finally { if (version === generation) content.removeAttribute('aria-busy'); }
    }
    form.addEventListener('submit', event => { event.preventDefault(); refresh(true); });
    form.addEventListener('change', event => {
        if (event.target === period && period.value !== 'custom') {
            const to = new Date(), from = new Date();
            if (period.value === 'month') from.setDate(1);
            else if (period.value !== 'today') from.setDate(from.getDate() - Number(period.value) + 1);
            form.elements.from.value = day(from); form.elements.to.value = day(to);
        } else if (event.target.name === 'from' || event.target.name === 'to') period.value = 'custom';
        if (event.target !== period || period.value !== 'custom') refresh(true);
    });
    pause.addEventListener('click', () => {
        paused = !paused;
        pause.setAttribute('aria-pressed', String(paused));
        const label = paused ? 'Reanudar actualización automática' : 'Pausar actualización automática';
        pause.setAttribute('aria-label', label); pause.title = label;
        pause.innerHTML = `<i class="bi bi-${paused ? 'play' : 'pause'}" aria-hidden="true"></i>`;
        if (!paused) refresh(); else updated.textContent += ' · pausada';
    });
    setInterval(() => {
        const unchanged = new URLSearchParams(new FormData(form)).toString() === applied.toString();
        if (!paused && unchanged && !document.hidden && !content.contains(document.activeElement)) refresh();
    }, 60000);
})();
