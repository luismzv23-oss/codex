(() => {
    'use strict';
    const shell = [...document.querySelectorAll('.sales-shell')].find(el => !el.dataset.ready);
    if (!shell) return;
    shell.dataset.ready = '1';
    const live = shell.dataset.salesLive === '1';
    const content = shell.querySelector('#sales-content') || shell;
    const states = new Map();
    const labels = table => {
        const heads = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(row => {
            let index = 0;
            [...row.cells].forEach(cell => {cell.dataset.label = cell.colSpan > 1 ? '' : heads[index] || 'Acciones'; index += cell.colSpan;});
        });
    };
    function initialize() {
        content.querySelectorAll('table').forEach(labels);
        if (!live) return;
        const groups = [...content.querySelectorAll('[data-sales-table]')].map(table => ({host:table, rows:[...(table.tBodies[0]?.rows || [])].filter(row => !row.querySelector('[colspan]'))}));
        const parents = new Set([...content.querySelectorAll('[data-sales-item],.data-item')].map(el => el.parentElement));
        parents.forEach(host => groups.push({host, rows:[...host.children].filter(el => el.matches('[data-sales-item],.data-item'))}));
        groups.forEach(({host,rows}, index) => {
            const key = host.id || host.closest('.card-body')?.querySelector('h2')?.textContent.trim() || String(index);
            host.querySelectorAll('.no-results-row,.no-data-row').forEach(el => el.remove());
            const state = states.get(key) || {page:1,query:''}; states.set(key,state);
            const card = host.closest('.card-body');
            const title = card?.querySelector('h2');
            let heading = title?.parentElement;
            let headerControls;
            if (title) {
                if (heading === card) {
                    heading = document.createElement('div');title.before(heading);heading.append(title);
                } else {
                    while (heading.parentElement !== card) heading = heading.parentElement;
                }
                heading.classList.add('sales-list-heading');
                let text = title.parentElement;
                if (text === heading) {
                    text = document.createElement('div');title.before(text);text.append(title);
                    if (text.nextElementSibling?.matches('p')) text.append(text.nextElementSibling);
                }
                text.classList.add('sales-list-description');
                headerControls = document.createElement('div');headerControls.className = 'sales-list-controls';
                [...heading.children].filter(child => child !== text).forEach(child => headerControls.append(child));
                heading.append(headerControls);
            }
            const search = document.createElement('label');search.className = 'sales-search';search.textContent = 'Buscar en este listado';
            const input = document.createElement('input');input.type='search';input.className='form-control form-control-sm';input.value=state.query;search.append(input);
            if (title) headerControls.append(search); else host.before(search);
            const pager = document.createElement('nav');pager.className='sales-pager';pager.setAttribute('aria-label','Páginas del listado');
            const summary = document.createElement('span');summary.setAttribute('aria-live','polite');
            const controls=document.createElement('div');const previous=document.createElement('button');const next=document.createElement('button');const position=document.createElement('span');
            [previous,next].forEach(button=>{button.type='button';button.className='btn btn-outline-secondary btn-sm';});
            previous.textContent='Anterior';next.textContent='Siguiente';controls.append(previous,position,next);pager.append(summary,controls);host.after(pager);
            const render=()=>{
                const global = content.querySelector('#salesSearchInput')?.value.toLocaleLowerCase() || '';
                const customer = host.id === 'receivables-table' ? content.querySelector('#receivable-customer-filter')?.value : '';
                const matches=rows.filter(row=>row.textContent.toLocaleLowerCase().includes(state.query.toLocaleLowerCase()) && row.textContent.toLocaleLowerCase().includes(global) && (!customer || row.dataset.customer === customer));
                const pages=Math.max(1,Math.ceil(matches.length/5));state.page=Math.min(state.page,pages);
                rows.forEach(row=>{row.hidden=true;});matches.slice((state.page-1)*5,state.page*5).forEach(row=>{row.hidden=false;});
                summary.textContent=matches.length ? `${(state.page-1)*5+1}–${Math.min(state.page*5,matches.length)} de ${matches.length}` : 'Sin registros';
                position.textContent=`Página ${state.page} de ${pages}`;previous.disabled=state.page===1;next.disabled=state.page===pages;
            };
            input.addEventListener('input',()=>{state.query=input.value;state.page=1;render();});
            previous.addEventListener('click',()=>{state.page--;render();});next.addEventListener('click',()=>{state.page++;render();});
            content.querySelector('#salesSearchInput')?.addEventListener('input',()=>{state.page=1;render();});
            content.querySelector('#receivable-customer-filter')?.addEventListener('change',()=>{state.page=1;render();});render();
        });
        const global=content.querySelector('#salesSearchInput');const clear=content.querySelector('#clearSalesSearchBtn');
        if(global && clear){const toggle=()=>{clear.style.display=global.value?'':'none';};global.addEventListener('input',toggle);clear.addEventListener('click',()=>{global.value='';global.dispatchEvent(new Event('input'));});toggle();}
    }
    initialize();
    if (!live) {
        let changed = false;
        shell.addEventListener('input', event => {if (event.target.closest('form')) changed = true;});
        shell.querySelector('[data-sales-refresh]')?.addEventListener('click', () => {
            if (!changed || window.confirm('Hay datos sin guardar. ¿Deseas actualizar esta pantalla y descartarlos?')) window.location.reload();
        });
        shell.querySelectorAll('tbody').forEach(body=>new MutationObserver(()=>labels(body.closest('table'))).observe(body,{childList:true}));
        return;
    }
    const status=shell.querySelector('#sales-status');let busy=false, queued=false, dirty=false, editingAction=false;
    content.addEventListener('input',event=>{if(event.target.closest('form[method="post"]'))editingAction=true;});
    shell.querySelectorAll('.sales-filters').forEach(form=>form.addEventListener('input',()=>{dirty=true;}));
    async function refresh(force=false){
        if(busy){if(force)queued=true;return;}
        if(!force && (document.hidden || dirty || editingAction || document.querySelector('.popup-overlay.is-open,.modal.show') || (shell.contains(document.activeElement) && document.activeElement.matches('input,textarea,select'))))return;
        busy=true;const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),15000);
        try{
            const response=await fetch(location.href,{cache:'no-store',signal:controller.signal});
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');const replacement=doc.querySelector('#sales-content');
            if(!response.ok || response.redirected || !replacement)throw new Error();
            const saved={};content.querySelectorAll('#salesSearchInput,#receivable-customer-filter').forEach(el=>{saved[el.id]=el.value;});
            content.replaceChildren(...replacement.childNodes);
            Object.entries(saved).forEach(([id,value])=>{const field=content.querySelector('#'+id);if(field)field.value=value;});
            const csrfName=doc.querySelector('.sales-shell input[type=hidden][name^="csrf"]')?.name;
            if(csrfName){const token=[...doc.querySelectorAll('input[type=hidden]')].find(el=>el.name===csrfName)?.value;shell.querySelectorAll('input[type=hidden]').forEach(el=>{if(el.name===csrfName)el.value=token;});}
            initialize();status.textContent='';
        }catch(_){status.textContent='No se pudo actualizar. Se conservan los datos; pulsa Actualizar para reintentar.';}
        finally{clearTimeout(timer);busy=false;if(queued){queued=false;refresh(true);}}
    }
    shell.querySelector('[data-sales-refresh]')?.addEventListener('click',()=>{if(dirty)shell.querySelector('.sales-filters')?.requestSubmit();else refresh(true);});
    window.addEventListener('codex:item-saved',()=>refresh(true));
    // Use existing POST routes; their redirects are followed in the background.
    shell.addEventListener('submit',async event=>{
        const form=event.target;if(!form.matches('form[method="post"]') || event.defaultPrevented)return;
        event.preventDefault();if(busy){status.textContent='Espera a que termine la actualización e inténtalo nuevamente.';return;}
        const data=new FormData(form);busy=true;const buttons=[...form.querySelectorAll('button')];buttons.forEach(el=>{el.disabled=true;});
        let message='';
        try{
            const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin'});
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');
            const error=doc.querySelector('.alert-danger');
            if(!response.ok || !doc.querySelector('.sales-shell') || error)throw new Error(error?.textContent.trim() || 'No se pudo confirmar la operación. Revisa el listado antes de reintentar.');
            message=doc.querySelector('.alert-success')?.textContent.trim() || 'Operación completada.';
            window.showCodexToast?.(message,'success');
        }catch(error){message=error.message;}
        finally{busy=false;editingAction=false;buttons.forEach(el=>{el.disabled=false;});await refresh(true);if(message)status.textContent=message;}
    });
    const timer=setInterval(()=>{if(!shell.isConnected)clearInterval(timer);else refresh();},60000);
})();
