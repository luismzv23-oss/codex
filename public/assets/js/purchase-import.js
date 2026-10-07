(() => {
    const root = document.getElementById('purchase-import');
    if (!root) return;
    let options = JSON.parse(document.getElementById('import-options').textContent);
    let documentData = null, draft = {}, step = 0, dirty = false, busy = false, itemPage = 1, archivePage = 1, documents = [];
    const $ = selector => root.querySelector(selector);
    const status = $('#import-status');
    const money = value => Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const round = (n, digits = 2) => Math.round((n + Number.EPSILON) * 10 ** digits) / 10 ** digits;
    const url = (path = '') => `${root.dataset.base}${path}?company_id=${encodeURIComponent(root.dataset.company)}`;
    function message(text, error = false) { status.textContent = text; status.classList.toggle('is-error', error); }
    async function api(path = '', method = 'GET', payload) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest', [root.dataset.csrfHeader]: root.dataset.csrf };
        let body;
        if (payload instanceof FormData) body = payload;
        else if (payload !== undefined) { body = JSON.stringify(payload); headers['Content-Type'] = 'application/json'; }
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), path.endsWith('/analizar') ? 120000 : 30000);
        try {
            const response = await fetch(url(path), { method, body, headers, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw Error('La sesión venció o la operación fue rechazada. Conserva tus cambios y vuelve a iniciar sesión.');
            const data = await response.json();
            if (data.csrf) root.dataset.csrf = data.csrf;
            if (!response.ok || data.error) throw Error(data.error || 'No se pudo completar la operación.');
            return data;
        } finally { clearTimeout(timer); }
    }
    async function work(task) {
        if (busy) return;
        busy = true; root.inert = true; root.setAttribute('aria-busy', 'true');
        const fields = [...root.querySelectorAll('button,input,select,textarea')].map(el => [el, el.disabled]);
        fields.forEach(([el]) => el.disabled = true);
        try { await task(); }
        catch (e) { message(e.name === 'AbortError' ? 'La operación tardó demasiado. El archivo ya cargado permanece en el archivo; vuelve a abrirlo antes de reintentar.' : e.message, true); }
        finally {
            busy = false; root.inert = false; root.removeAttribute('aria-busy'); fields.forEach(([el, disabled]) => el.disabled = disabled);
            updateTotals();
        }
    }
    function selectValues(select, rows, placeholder) {
        const value = select.value;
        select.replaceChildren(new Option(placeholder, ''));
        rows.forEach(([id, name]) => select.add(new Option(name, id)));
        select.value = value;
    }
    function hydrate() {
        selectValues($('[data-field=supplier_id]'), options.suppliers.map(s => [s.id, `${s.name}${s.tax_id ? ' · ' + s.tax_id : ''}`]), 'Selecciona el proveedor');
        selectValues($('[data-field=currency_code]'), Object.entries(options.currencies), 'Selecciona moneda');
        receiptOptions();
        root.querySelectorAll('[data-field]').forEach(el => el.value = draft[el.dataset.field] ?? '');
        $('#import-reviewed').checked = Boolean(draft.reviewed);
        $('#import-text').textContent = draft.source_text || 'No hay texto reconocido. Completa los campos consultando el original.';
        let warning = $('#import-extraction-warning');
        if (!warning) { warning = document.createElement('p'); warning.id = 'import-extraction-warning'; warning.className = 'alert alert-warning'; status.after(warning); }
        warning.textContent = draft.extraction_warning || '';
        warning.hidden = !draft.extraction_warning;
        renderItems();
    }
    function receiptOptions() {
        const list = options.receipts.filter(r => r.supplier_id === draft.supplier_id && r.currency_code === draft.currency_code);
        selectValues($('[data-field=purchase_receipt_id]'), list.map(r => [r.id, r.receipt_number]), 'Sin recepción asociada');
        $('[data-field=purchase_receipt_id]').value = draft.purchase_receipt_id || '';
    }
    function changed() {
        dirty = true; draft.reviewed = false; $('#import-reviewed').checked = false;
        $('#import-save-status').textContent = 'Cambios sin guardar'; updateTotals();
    }
    root.querySelectorAll('[data-field]').forEach(el => el.addEventListener('input', () => {
        draft[el.dataset.field] = el.value;
        if (['supplier_id', 'currency_code'].includes(el.dataset.field)) { draft.purchase_receipt_id = ''; receiptOptions(); }
        changed();
    }));
    $('#import-reviewed').addEventListener('change', e => { draft.reviewed = e.target.checked; dirty = true; updateTotals(); });
    function go(next) {
        step = next;
        root.querySelectorAll('[data-step]').forEach(el => el.hidden = Number(el.dataset.step) !== step);
        root.querySelectorAll('[data-step-label]').forEach(el => {
            if (Number(el.dataset.stepLabel) === step) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
        });
        $('#import-success').hidden = true;
        $('#import-preview').hidden = !documentData || step === 0;
        $('#import-footer').hidden = step === 0;
        $('#import-next').textContent = step === 3 ? 'Registrar factura' : 'Continuar →';
        updateTotals();
    }
    function load(data) {
        documentData = data;
        draft = { supplier_id: '', invoice_number: '', issue_date: '', due_date: '', currency_code: 'ARS', exchange_rate: 1, items: [], ...data.draft };
        dirty = false; itemPage = 1;
        const source = url(`/${data.id}/archivo`);
        showPreview(1);
        $('#import-original').href = `${source}&download=1`;
        $('#import-download').href = `${source}&download=1`;
        $('#import-filename').textContent = data.name;
        hydrate();
        if (data.status === 'registered') success(); else go(1);
        $('#import-save-status').textContent = 'Borrador guardado';
    }
    function showPreview(page) {
        $('#import-frame').src = url(`/${documentData.id}/vista/${page}`);
        const host = $('#import-preview-pages'); host.replaceChildren();
        const pages = Math.max(1, Number(draft.pages || 1));
        const label = document.createElement('span'); label.textContent = `Página ${page} de ${pages}`;
        const controls = document.createElement('div');
        for (const [text, next] of [['Anterior', page - 1], ['Siguiente', page + 1]]) {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-dark'; button.textContent = text; button.disabled = next < 1 || next > pages;
            button.addEventListener('click', () => showPreview(next)); controls.append(button);
        }
        host.append(label, controls);
    }
    $('#import-frame').addEventListener('error', () => { message('No se pudo generar la vista previa. Puedes descargar el original para revisarlo.', true); });
    async function save() {
        if (!documentData || documentData.status !== 'draft') return;
        const data = await api(`/${documentData.id}/borrador`, 'POST', { draft, revision: documentData.revision });
        documentData = data; dirty = false;
        $('#import-save-status').textContent = 'Guardado ' + new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
    }
    function totals() {
        let net = 0, tax = 0;
        for (const row of draft.items || []) {
            const quantity = round(Number(row.quantity) * Number(row.pack || 1));
            const cost = round(Number(row.unit_cost) * (1 - Number(row.discount || 0) / 100) / Number(row.pack || 1), 4);
            const subtotal = round(quantity * cost);
            net += subtotal; tax += round(subtotal * round(Number(row.tax_rate || 0)) / 100);
        }
        return { net: round(net), tax: round(tax), total: round(net + tax) };
    }
    function updateTotals() {
        const t = totals();
        for (const target of ['#import-item-totals', '#import-review-totals']) {
            const host = $(target); host.replaceChildren();
            for (const [title, amount] of [['Neto calculado', t.net], ['IVA calculado', t.tax], ['Total calculado', t.total]]) {
                const box = document.createElement('div'), label = document.createElement('span'), value = document.createElement('strong');
                label.textContent = title; value.textContent = money(amount); box.append(label, value); host.append(box);
            }
        }
        const match = draft.items?.length && [['expected_subtotal', t.net], ['expected_tax', t.tax], ['expected_total', t.total]].every(([key, amount]) => draft[key] !== '' && draft[key] != null && Number.isFinite(Number(draft[key])) && Math.abs(Number(draft[key]) - amount) <= .011);
        $('#import-difference').textContent = match ? 'Los importes coinciden.' : 'Completa los importes del original y resuelve las diferencias para continuar.';
        $('#import-difference').className = match ? 'import-difference-ok' : 'import-difference-error';
        $('#import-next').disabled = busy || (step === 3 && (!match || !draft.reviewed));
    }
    function pager(host, page, count, callback) {
        host.replaceChildren(); const pages = Math.max(1, Math.ceil(count / 5));
        if (pages <= 1) return;
        const label = document.createElement('span'); label.textContent = `Página ${page} de ${pages} · ${count} registros`;
        const buttons = document.createElement('div');
        for (const [text, n] of [['Anterior', page - 1], ['Siguiente', page + 1]]) {
            const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-dark'; b.textContent = text; b.disabled = n < 1 || n > pages;
            b.addEventListener('click', () => callback(n)); buttons.append(b);
        }
        host.append(label, buttons);
    }
    function renderItems() {
        const host = $('#import-items'); host.replaceChildren();
        draft.items ||= [];
        itemPage = Math.max(1, Math.min(itemPage, Math.ceil(draft.items.length / 5) || 1));
        draft.items.slice((itemPage - 1) * 5, itemPage * 5).forEach((row, offset) => {
            const index = (itemPage - 1) * 5 + offset;
            const card = document.createElement('div'); card.className = 'import-item';
            const head = document.createElement('div'); head.className = 'import-item-head';
            const title = document.createElement('strong'); title.textContent = `Renglón ${index + 1}`;
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger'; remove.textContent = 'Quitar';
            remove.addEventListener('click', () => { draft.items.splice(index, 1); changed(); renderItems(); }); head.append(title, remove); card.append(head);
            const fields = document.createElement('div'); fields.className = 'import-fields';
            const productLabel = document.createElement('label'); productLabel.className = 'wide'; productLabel.textContent = 'Vincular producto';
            const select = document.createElement('select'); select.className = 'form-select';
            const search = document.createElement('input'); search.type = 'search'; search.className = 'form-control'; search.placeholder = 'Buscar por código o nombre'; search.setAttribute('aria-label', `Buscar producto del renglón ${index + 1}`);
            const fillProducts = () => {
                select.replaceChildren(new Option('Sin vínculo · concepto libre', ''));
                const query = search.value.trim().toLocaleLowerCase();
                options.products.filter(p => p.id === row.product_id || `${p.sku} ${p.name}`.toLocaleLowerCase().includes(query)).forEach(p => select.add(new Option(`${p.sku} · ${p.name}`, p.id)));
                select.value = row.product_id || '';
            };
            search.addEventListener('input', fillProducts); fillProducts(); productLabel.append(search);
            select.addEventListener('change', () => { row.product_id = select.value; changed(); }); productLabel.append(select); fields.append(productLabel);
            for (const [key, title, type, precision, min, initial] of [
                ['description', 'Descripción', 'text', '', '', ''], ['quantity', 'Cantidad facturada', 'number', '.01', '.01', 1],
                ['unit_cost', 'Costo neto por unidad facturada', 'number', '.0001', '0', 0], ['tax_rate', 'IVA %', 'number', '.01', '0', 21],
                ['pack', 'Unidades de catálogo por unidad facturada', 'number', '.01', '.01', 1], ['discount', 'Descuento %', 'number', '.01', '0', 0]
            ]) {
                const label = document.createElement('label'); label.textContent = title; if (type === 'text') label.className = 'wide';
                const input = document.createElement('input'); input.className = 'form-control'; input.type = type; input.value = row[key] ?? initial;
                if (type === 'number') { input.step = precision; input.min = min; } else input.maxLength = 255;
                input.addEventListener('input', () => { row[key] = input.value; changed(); }); label.append(input); fields.append(label);
            }
            card.append(fields); host.append(card);
        });
        if (!draft.items.length) { const empty = document.createElement('p'); empty.textContent = 'Agrega los productos del documento. Si no se reconocieron renglones fiables, complétalos manualmente.'; host.append(empty); }
        pager($('#import-items-pager'), itemPage, draft.items.length, page => { itemPage = page; renderItems(); }); updateTotals();
    }
    function validStep() {
        if (step === 1) {
            if (!draft.supplier_id || !draft.invoice_number?.trim() || !draft.issue_date || !draft.currency_code || Number(draft.exchange_rate) <= 0) throw Error('Completa proveedor, número, fecha, moneda y cotización.');
        }
        if (step === 2) {
            if (!draft.items?.length) throw Error('Agrega al menos un renglón.');
            draft.items.forEach((row, index) => {
                if (!row.description?.trim() || ![row.quantity, row.unit_cost, row.tax_rate, row.pack ?? 1, row.discount ?? 0].every(v => v !== '' && Number.isFinite(Number(v))) || Number(row.quantity) <= 0 || Number(row.unit_cost) < 0 || Number(row.tax_rate) < 0 || Number(row.tax_rate) > 100 || Number(row.pack ?? 1) <= 0 || Number(row.discount || 0) < 0 || Number(row.discount || 0) > 100) throw Error(`Revisa los datos del renglón ${index + 1}.`);
            });
        }
    }
    function success() {
        root.querySelectorAll('[data-step]').forEach(el => el.hidden = true);
        $('#import-success').hidden = false; $('#import-footer').hidden = true; $('#import-preview').hidden = false;
        dirty = false; message('El documento original está archivado y vinculado a la factura.');
    }
    async function upload(file) {
        if (!file || busy) return;
        if (!/\.(pdf|jpe?g|png)$/i.test(file.name) || file.size > 10 * 1024 * 1024) { message('Selecciona PDF, JPG, JPEG o PNG de hasta 10 MB.', true); return; }
        await work(async () => {
            message('Guardando el original en el archivo cifrado…');
            const form = new FormData(); form.append('document', file);
            const data = await api('', 'POST', form); load(data);
            if (data.status === 'registered') { message('Este archivo ya corresponde a una factura registrada.'); return; }
            if (!data.revision) {
                message('Leyendo el documento… Si es una imagen o un escaneo, el reconocimiento puede tardar unos segundos.');
                try { load(await api(`/${data.id}/analizar`, 'POST', {})); message('Lectura terminada. Revisa las sugerencias junto al original.'); }
                catch (e) { message(e.message + ' El original quedó guardado y puedes completar los campos manualmente.', true); }
            } else message('Este archivo ya estaba archivado. Recuperamos su borrador.');
            await archive();
        });
    }
    async function archive() { const data = await api(); documents = data.documents; renderArchive(); }
    function renderArchive() {
        const query = $('#import-search').value.toLocaleLowerCase();
        const rows = documents.filter(d => d.original_name.toLocaleLowerCase().includes(query));
        archivePage = Math.max(1, Math.min(archivePage, Math.ceil(rows.length / 5) || 1));
        const host = $('#import-documents'); host.replaceChildren();
        rows.slice((archivePage - 1) * 5, archivePage * 5).forEach(row => {
            const div = document.createElement('div'); div.className = 'import-doc-row';
            const details = document.createElement('div'), name = document.createElement('strong'), meta = document.createElement('small');
            name.textContent = row.original_name; meta.textContent = `${row.status === 'registered' ? 'Registrada' : 'Borrador'} · ${row.created_at}`; details.append(name, meta);
            const open = document.createElement('button'); open.type = 'button'; open.className = 'btn btn-sm btn-outline-dark'; open.textContent = row.status === 'registered' ? 'Consultar' : 'Continuar';
            open.addEventListener('click', () => work(async () => { load(await api(`/${row.id}`)); message('Documento recuperado del archivo seguro.'); })); div.append(details, open); host.append(div);
        });
        if (!rows.length) host.textContent = 'No hay documentos para mostrar.';
        pager($('#import-documents-pager'), archivePage, rows.length, n => { archivePage = n; renderArchive(); });
    }
    $('#import-search').addEventListener('input', () => { archivePage = 1; renderArchive(); });
    $('#import-file').addEventListener('change', event => upload(event.target.files[0]));
    const drop = $('#import-drop');
    ['dragover', 'dragenter'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.add('is-over'); }));
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', event => { event.preventDefault(); drop.classList.remove('is-over'); upload(event.dataTransfer.files[0]); });
    $('#import-add').addEventListener('click', () => {
        if (draft.items.length >= 200) { message('Puedes agregar hasta 200 renglones.', true); return; }
        draft.items.push({ product_id: '', description: '', quantity: 1, unit_cost: 0, tax_rate: 21, pack: 1, discount: 0 }); itemPage = Math.ceil(draft.items.length / 5); changed(); renderItems();
    });
    $('#import-back').addEventListener('click', () => work(async () => { await save(); go(Math.max(0, step - 1)); if (step === 0) await archive(); message(''); }));
    $('#import-save').addEventListener('click', () => work(async () => { await save(); message('Borrador guardado. Puedes retomarlo desde el archivo de facturas.'); }));
    $('#import-next').addEventListener('click', () => work(async () => {
        validStep(); await save();
        if (step < 3) { go(step + 1); message(''); return; }
        message('Registrando la factura y vinculando el original…');
        documentData = await api(`/${documentData.id}/confirmar`, 'POST', { revision: documentData.revision }); success();
        if (window.parent !== window) window.parent.dispatchEvent(new CustomEvent('codex:item-saved', { detail: { entity: 'purchase-invoice' } }));
    }));
    $('#import-another').addEventListener('click', () => work(async () => { documentData = null; draft = {}; dirty = false; $('#import-file').value = ''; $('#import-frame').removeAttribute('src'); go(0); message(''); await archive(); }));
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('codex:item-saved', () => work(async () => { options = await api('/opciones'); hydrate(); message('Catálogo actualizado. Selecciona el proveedor o producto nuevo.'); }));
    go(0); work(archive);
})();
