(() => {
    'use strict';
    const root = document.querySelector('#dashboard-workspace');
    if (!root) return;
    const $ = selector => root.querySelector(selector);
    const config = JSON.parse(document.querySelector('#dw-data').textContent);
    const modules = config.modules || [];
    let selected = modules[0], mode = 'bars', area = '', state = '', day = '';
    const colors = ['#4e64d6', '#e7ab55', '#57a797', '#9675c8', '#d57a88', '#6c89a8'];
    const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    const dateLabel = iso => iso.slice(8, 10) + '/' + iso.slice(5, 7);
    $('#dw-refresh')?.addEventListener('click', () => { $('#dw-update-status').textContent = '· Actualizando…'; location.reload(); });
    let activityPage = 1, tasksPage = 1;
    function paginate(items, page, size, id) {
        const pages = Math.max(1, Math.ceil(items.length / size));
        page = Math.max(1, Math.min(page, pages));
        const start = (page - 1) * size;
        items.forEach((item, index) => { item.hidden = index < start || index >= start + size; });
        const pager = $(id);
        if (pager) {
            pager.hidden = items.length === 0;
            pager.querySelector('[data-page-info]').textContent = `${start + 1}–${Math.min(start + size, items.length)} de ${items.length} · Página ${page} de ${pages}`;
            pager.querySelector('[data-page-prev]').disabled = page === 1;
            pager.querySelector('[data-page-next]').disabled = page === pages;
        }
        return page;
    }
    function filterActivity(reset = true) {
        if (!$('#dw-activity-body')) return;
        if (reset) activityPage = 1;
        const query = $('#dw-search').value.toLocaleLowerCase('es');
        const matches = [];
        $('#dw-activity-body').querySelectorAll('tr').forEach(row => {
            row.hidden = !!((area && row.dataset.area !== area) || (state && row.dataset.status !== state) || (day && row.dataset.day !== day) || !row.textContent.toLocaleLowerCase('es').includes(query));
            if (!row.hidden) matches.push(row);
        });
        const count = matches.length;
        activityPage = paginate(matches, activityPage, 10, '#dw-activity-pagination');
        $('#dw-no-results').hidden = count > 0;
        $('#dw-activity-count').textContent = `${count} registros recientes encontrados · Máximo 8 por área`;
        $('#dw-activity-scope').textContent = [area ? modules.find(m => m.id === area)?.name : 'Todas las áreas autorizadas', state, day ? dateLabel(day) : ''].filter(Boolean).join(' · ');
    }
    function selectPoint(index) {
        const point = selected.series[index];
        day = point.day; area = selected.id; state = '';
        $('#dw-point').textContent = `${dateLabel(point.day)}: ${point.value} registros. Actividad reciente filtrada por ese día.`;
        filterActivity();
    }
    function renderChart() {
        if (!selected) return;
        $('#dw-chart-title').textContent = selected.name + ' · Evolución diaria';
        $('#dw-chart-total').textContent = selected.total.toLocaleString('es-AR');
        const points = selected.series;
        const ceiling = Math.max(1, ...points.map(p => p.value));
        const width = 680, left = 42, span = 610, baseline = 190, height = 162;
        const step = span / Math.max(1, points.length);
        let svg = `<svg viewBox="0 0 ${width} 224" role="group" aria-label="Registros diarios de ${escape(selected.name)}">`;
        [0, .5, 1].forEach(f => {
            const y = baseline - height * f;
            svg += `<line x1="${left}" y1="${y}" x2="${left+span}" y2="${y}" stroke="#e7ecf4" stroke-dasharray="3 5"/><text x="32" y="${y+4}" fill="#697b96" font-size="10" text-anchor="end">${Math.round(ceiling*f)}</text>`;
        });
        const coords = points.map((p,i) => [left + step*(i+.5), baseline - height*p.value/ceiling]);
        if (mode === 'line') svg += `<polyline points="${coords.map(c=>c.join(',')).join(' ')}" fill="none" stroke="#4e64d6" stroke-width="2.5" stroke-linejoin="round"/>`;
        points.forEach((point,i) => {
            const [x,y] = coords[i];
            const attrs = `data-point="${i}" tabindex="${i === 0 ? '0' : '-1'}" role="button" aria-label="${point.day}: ${point.value} registros. Enter para filtrar; flechas para recorrer."`;
            svg += mode === 'bars' ? `<rect ${attrs} x="${x-step*.32}" y="${point.value ? y : baseline-2}" width="${Math.max(3,step*.64)}" height="${Math.max(2,baseline-y)}" rx="2" fill="${point.value ? '#697fdd' : '#d5ddec'}"><title>${point.day}: ${point.value} registros</title></rect>` : `<circle ${attrs} cx="${x}" cy="${y}" r="${points.length>30?3:4}" fill="#4e64d6"><title>${point.day}: ${point.value} registros</title></circle>`;
            if (i === 0 || i === points.length-1 || i % Math.ceil(points.length/6) === 0) svg += `<text x="${x}" y="214" text-anchor="middle" font-size="10" fill="#697b96">${dateLabel(point.day)}</text>`;
        });
        $('#dw-chart').innerHTML = svg + '</svg>';
        $('#dw-series-table').innerHTML = points.map(p=>`<tr><td>${escape(p.day)}</td><td>${p.value}</td></tr>`).join('');
        $('#dw-point').textContent = selected.total ? 'Selecciona un día para filtrar la actividad. Usa las flechas para recorrer la gráfica.' : 'No hay registros en este período. Los días sin actividad se muestran en cero.';
        const total = selected.statuses.reduce((sum,s)=>sum+s.value,0);
        let offset=0;
        const parts=selected.statuses.map((s,i)=>{const begin=offset;offset+=total?s.value/total*100:0;return `${colors[i%colors.length]} ${begin}% ${offset}%`;});
        $('#dw-ring').style.background = total ? `conic-gradient(${parts.join(',')})` : '#edf1f7';
        $('#dw-ring-total').textContent = total.toLocaleString('es-AR');
        $('#dw-state-note').textContent = selected.status_note;
        $('#dw-legend').innerHTML = selected.statuses.map((s,i)=>{
            const tag=selected.id==='inventario'?'div':'button';
            return `<${tag} ${tag==='button'?`type="button" data-state="${escape(s.label)}" aria-pressed="false"`:''}><i style="background:${colors[i%colors.length]}" aria-hidden="true"></i><span>${escape(s.label)}</span><strong>${s.value}</strong><small>${total?Math.round(s.value/total*100):0}%</small></${tag}>`;
        }).join('') || '<p class="dw-note">Sin registros en este período.</p>';
    }
    root.addEventListener('click',event=>{
        const point = event.target.closest('[data-point]');
        if (point) { selectPoint(Number(point.dataset.point)); return; }
        const button = event.target.closest('button'); if (!button) return;
        if (button.dataset.dwModule) {
            selected=modules.find(m=>m.id===button.dataset.dwModule); area=selected.id; state=''; day='';
            root.querySelectorAll('[data-dw-module]').forEach(b=>{b.classList.toggle('is-active',b===button);b.setAttribute('aria-pressed',String(b===button));});
            renderChart(); filterActivity();
        }
        if (button.dataset.dwMode) { mode=button.dataset.dwMode; root.querySelectorAll('[data-dw-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));renderChart(); }
        if (button.dataset.state) { state=state===button.dataset.state?'':button.dataset.state;area=selected.id;day='';root.querySelectorAll('[data-state]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.state===state)));filterActivity(); }
    });
    root.addEventListener('keydown',event=>{
        const point=event.target.closest('[data-point]');if(!point)return;
        const index=Number(point.dataset.point);
        if(event.key==='Enter'||event.key===' '){event.preventDefault();selectPoint(index);}
        if(['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){
            event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?selected.series.length-1:Math.max(0,Math.min(selected.series.length-1,index+(event.key==='ArrowLeft'?-1:1)));
            root.querySelectorAll('[data-point]').forEach(p=>p.setAttribute('tabindex',p.dataset.point===String(next)?'0':'-1'));
            root.querySelector(`[data-point="${next}"]`).focus();
            const p=selected.series[next];$('#dw-point').textContent=`${dateLabel(p.day)}: ${p.value} registros. Enter para filtrar.`;
        }
    });
    $('#dw-search')?.addEventListener('input',filterActivity);
    $('#dw-clear')?.addEventListener('click',()=>{area='';state='';day='';$('#dw-search').value='';root.querySelectorAll('[data-state]').forEach(b=>b.setAttribute('aria-pressed','false'));filterActivity();});
    function renderTasks() {
        tasksPage = paginate(Array.from(root.querySelectorAll('[data-dw-task]')), tasksPage, 5, '#dw-tasks-pagination');
    }
    [['#dw-activity-pagination', true], ['#dw-tasks-pagination', false]].forEach(([id, activity]) => {
        $(id)?.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button || button.disabled) return;
            const delta = button.hasAttribute('data-page-next') ? 1 : -1;
            if (activity) { activityPage += delta; filterActivity(false); }
            else { tasksPage += delta; renderTasks(); }
        });
    });
    filterActivity();
    renderTasks();
    renderChart();
})();
