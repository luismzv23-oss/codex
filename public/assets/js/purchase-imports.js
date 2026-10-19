import {money,totals,mergeSuggestions,highlightRect} from './purchase-import-utils.mjs';
const configNode=document.getElementById('import-config');
if(configNode) {
    const config=JSON.parse(configNode.textContent), byId=id=>document.getElementById(id);
    let draft=null,stage=1,dirty=false,revision=0,savePromise=null,saveTimer=null,pollTimer=null,blocked=false;
    let csrf=config.csrfToken,confirmationKey='',uploading=false,confirming=false,pdf=null,page=1,renderTask=null,previewEpoch=0;
    const data=()=>draft?.data || {};
    const url=(suffix='')=>{const value=new URL(config.baseUrl+suffix,window.location.href);value.searchParams.set('company_id',config.companyId);return value;};
    const fileUrl=()=>url('/'+encodeURIComponent(draft.id)+'/archivo').href;
    function message(text,error=false) {
        for(const id of ['import-message','import-inline-message']) {
            const node=byId(id);if(!node) continue;
            node.textContent=text;node.hidden=!text;node.classList.toggle('is-error',error);
        }
    }
    async function request(suffix,method='GET',body=null) {
        const abort=new AbortController(),timeout=setTimeout(()=>abort.abort(),45000);
        const headers={Accept:'application/json','X-Requested-With':'XMLHttpRequest'};
        if(method!=='GET') headers[config.csrfHeader]=csrf;
        if(body && !(body instanceof FormData)) headers['Content-Type']='application/json';
        try {
            const response=await fetch(url(suffix),{method,headers,credentials:'same-origin',cache:'no-store',signal:abort.signal,
                body:body instanceof FormData?body:body?JSON.stringify(body):undefined});
            const result=await response.json();
            if(result.csrf_token) csrf=result.csrf_token;
            if(!response.ok) {const error=new Error(result.error || 'No se pudo completar la operación.');error.status=response.status;throw error;}
            return result;
        } catch(error) {
            if(error.name==='AbortError') throw new Error('La operación tardó demasiado. Conservamos los datos en pantalla; actualiza el estado antes de volver a registrar.');
            if(error instanceof SyntaxError) throw new Error('El servidor no devolvió una respuesta válida. Comprueba la sesión y conserva los datos antes de recargar.');
            throw error;
        } finally {clearTimeout(timeout);}
    }
    function setStage(next) {
        stage=Math.max(draft?2:1,Math.min(5,next));
        document.querySelectorAll('[data-import-step]').forEach(node=>{
            if(Number(node.dataset.importStep)===stage) node.setAttribute('aria-current','step');else node.removeAttribute('aria-current');
        });
        byId('import-reading-panel').hidden=stage!==2;byId('import-header-panel').hidden=stage!==3;
        byId('import-link-panel').hidden=stage!==4;byId('import-confirm-panel').hidden=stage!==5;
        byId('import-prev').disabled=stage<=2;byId('import-next').hidden=stage>=5;
        byId('import-stage-title').textContent=({2:'Leer el documento',3:'Datos de la factura',4:'Conceptos y recepción',5:'Revisión final'})[stage] || 'Subir factura';
        byId('import-stage-help').textContent=stage===2?'Puedes continuar aunque la lectura esté pendiente.':stage===5?'El registro se confirma una sola vez.':'Los cambios se guardan como borrador.';
        updateTotals();if(stage===5) renderSummary();
    }
    function renderConfidence() {
        document.querySelectorAll('[data-confidence]').forEach(node=>{
            const key=node.dataset.confidence,location=draft?.extraction?.locations?.[key];
            const suggested=draft?.extraction?.suggestions?.[key];
            const match=suggested!==undefined && String(data()[key] ?? '')===String(suggested);
            const detected=location?.detection_status==='detected' || (location?.confidence!=null && Number(location.confidence)>=.9);
            node.textContent=location?(match?(detected?'Detectado':'Revisar'):'Revisar: '+String(suggested ?? '').slice(0,24)):data()[key]?'':'Completar';
            node.classList.toggle('is-detected',Boolean(location && match && detected));
            node.title=location?'La lectura es orientativa; comprueba el original.':'';
        });
    }
    function processing() {
        if(!draft) return;
        const names={idle:'Documento pendiente de lectura',queued:'Lectura solicitada · el procesador se ejecuta cada minuto',reading:'Leyendo documento',done:'Lectura disponible para revisar',error:'Lectura interrumpida: puedes continuar manualmente'};
        byId('import-processing').textContent=names[draft.processing_status] || '';
        byId('import-apply').hidden=!draft.extraction?.suggestions || draft.review_status==='registered';
        byId('import-analyze').disabled=!config.ocrConfigured || ['queued','reading'].includes(draft.processing_status) || draft.review_status==='registered';
        if(draft.last_error) message(draft.last_error,true);
        else if(draft.processing_status==='done' && draft.extraction?.notice) message(draft.extraction.notice);
        if(draft.review_status!=='registered' && ['queued','reading'].includes(draft.processing_status)) schedulePoll();else clearTimeout(pollTimer);
    }
    function schedulePoll() {
        clearTimeout(pollTimer);
        pollTimer=setTimeout(async()=>{
            if(!draft || document.hidden) {schedulePoll();return;}
            try {
                const id=draft.id;const result=await request('/'+id),remote=result.draft;
                if(!draft || draft.id!==id) return;
                if(remote.version>draft.version && !savePromise && !confirming) {
                    blocked=true;message('El borrador cambió en otra sesión. Descarga una copia de tus datos y actualiza antes de continuar.',true);return;
                }
                if(remote.version<draft.version) {schedulePoll();return;}
                const justCompleted=remote.processing_status==='done' && draft.processing_status!=='done';
                draft.processing_status=remote.processing_status;draft.extraction=remote.extraction;draft.last_error=remote.last_error;
                processing();renderConfidence();
                if(justCompleted) applyDetectedSuggestions();
            } catch(error) {message(error.message,true);schedulePoll();}
        },3500);
    }
    function changed() {
        if(!draft || draft.review_status==='registered') return;
        dirty=true;revision++;byId('import-save-status').textContent='Cambios por guardar';
        clearTimeout(saveTimer);if(!blocked) saveTimer=setTimeout(()=>save().catch(error=>message(error.message,true)),1000);
        updateTotals();renderConfidence();
    }
    async function save() {
        if(!draft || draft.review_status==='registered') return;
        if(blocked) throw new Error('Actualiza el borrador antes de guardar. Puedes descargar una copia de lo que estás editando.');
        if(savePromise) {await savePromise;if(dirty) return save();return;}
        if(!dirty) return;
        const captured=revision,body={version:draft.version,data:structuredClone(data())};
        byId('import-save-status').textContent='Guardando…';
        savePromise=request('/'+draft.id,'PATCH',body).then(result=>{
            draft.version=result.draft.version;draft.review_status=result.draft.review_status;
            if(revision===captured) dirty=false;
            byId('import-save-status').textContent=dirty?'Cambios por guardar':'Guardado';
        }).catch(error=>{
            blocked=true;byId('import-save-status').textContent='Sin guardar';
            throw error;
        }).finally(()=>{savePromise=null;});
        await savePromise;
    }
    function writeField(control) {
        const key=control.dataset.field;
        data()[key]=control.type==='checkbox'?control.checked:control.value;
        if(key!=='identity_reviewed') {
            data().identity_reviewed=false;
            const review=document.querySelector('[data-field="identity_reviewed"]');if(review) review.checked=false;
        }
        if(key==='supplier_id') {
            const supplier=config.suppliers.find(s=>s.id===control.value);
            if(supplier && !data().supplier_tax_id) {data().supplier_tax_id=supplier.tax_id || '';document.querySelector('[data-field="supplier_tax_id"]').value=data().supplier_tax_id;}
        }
        if(key==='purchase_kind' && control.value!=='goods') {data().purchase_receipt_id='';document.querySelector('[data-field="purchase_receipt_id"]').value='';}
        if(key==='purchase_kind' && control.value==='service') {for(const item of data().items || []) item.product_id='';renderItems();}
        if(['supplier_id','currency_code','purchase_kind'].includes(key)) filterReceipts();
        changed();
    }
    function filterReceipts() {
        const select=document.querySelector('[data-field="purchase_receipt_id"]');
        select.disabled=data().purchase_kind!=='goods' || draft?.review_status==='registered';
        for(const option of select.options) if(option.value) option.disabled=option.dataset.supplier!==data().supplier_id || option.dataset.currency!==data().currency_code;
    }
    function renderFields() {
        document.querySelectorAll('[data-field]').forEach(control=>{
            const value=data()[control.dataset.field];
            if(control.type==='checkbox') control.checked=Boolean(value);else control.value=String(value ?? '').slice(0,control.type==='date'?10:10000);
            control.disabled=draft.review_status==='registered';
        });filterReceipts();renderConfidence();
    }
    const productLabel=product=>product.sku+' · '+product.name;
    let itemsPage=1;
    const itemsPageSize=1;
    function paginateItems() {
        const rows=Array.from(byId('import-items').children),total=rows.length;
        const pages=Math.max(1,Math.ceil(total/itemsPageSize));
        itemsPage=Math.min(Math.max(1,itemsPage),pages);
        const start=(itemsPage-1)*itemsPageSize;
        rows.forEach((row,index)=>{row.hidden=index<start || index>=start+itemsPageSize;});
        byId('import-items-page-status').textContent=total ? `Conceptos ${start+1}–${Math.min(start+itemsPageSize,total)} de ${total}` : 'Sin conceptos';
        byId('import-items-page-number').textContent=`${itemsPage} / ${pages}`;
        byId('import-items-prev').disabled=itemsPage===1;
        byId('import-items-next').disabled=itemsPage===pages;
    }
    byId('import-items-prev').addEventListener('click',()=>{itemsPage--;paginateItems();});
    byId('import-items-next').addEventListener('click',()=>{itemsPage++;paginateItems();});
    function renderItems() {
        const container=byId('import-items');container.replaceChildren();
        (data().items || []).forEach((item,index)=>{
            const row=document.createElement('div');row.className='invoice-item';
            row.innerHTML='<div class="invoice-item-head"><label class="invoice-field"><span>Descripción</span><input class="form-control" data-item-field="description" maxlength="500"></label><button type="button" class="btn btn-outline-danger icon-btn" title="Quitar concepto" aria-label="Quitar concepto"><i class="bi bi-trash"></i></button></div><div class="invoice-item-numbers"><label class="invoice-field"><span>Cantidad</span><input class="form-control" data-item-field="quantity" type="number" min="0.000001" step="0.000001"></label><label class="invoice-field"><span>Costo neto unitario</span><input class="form-control" data-item-field="unit_cost" type="number" min="0" step="0.000001"></label><label class="invoice-field"><span>IVA %</span><input class="form-control" data-item-field="tax_rate" type="number" min="0" max="100" step="0.0001"></label><label class="invoice-field"><span>Importe IVA</span><input class="form-control" data-item-field="tax_amount" type="number" min="0" step="0.01" placeholder="Calculado"></label></div><div class="invoice-item-link"><label class="invoice-field"><span>Producto interno</span><input class="form-control" data-product-search list="import-products" placeholder="Busca por código o nombre"></label><label class="invoice-field"><span>Código del proveedor</span><input class="form-control" data-item-field="supplier_code" maxlength="120"></label></div>';
            row.querySelectorAll('[data-item-field]').forEach(control=>{
                const key=control.dataset.itemField;control.value=item[key] ?? '';control.disabled=draft.review_status==='registered';
                if(key==='quantity') {
                    control.min='1';control.step='1';
                    if(control.value!=='') control.value=String(Number(control.value));
                }
                if(['unit_cost','tax_rate'].includes(key)) {
                    control.step='0.01';
                    if(control.value!=='') control.value=Number(control.value).toFixed(2);
                }
                if(key==='tax_amount') control.readOnly=true;
                if(['quantity','unit_cost','tax_rate'].includes(key)) {
                    control.addEventListener('change',()=>{
                        if(control.value!=='' && Number.isFinite(Number(control.value))) {
                            control.value=key==='quantity'?String(Number(control.value)):Number(control.value).toFixed(2);
                            item[key]=control.value;
                            refreshItemTax();changed();
                        }
                    });
                }
                control.addEventListener('focus',()=>highlight('items.'+index+'.'+key));
                control.addEventListener('input',()=>{
                    item[key]=control.value;if(['quantity','unit_cost','tax_rate'].includes(key)) {item.tax_amount='';refreshItemTax();}
                    data().identity_reviewed=false;changed();
                });
            });
            function refreshItemTax() {
                const quantity=row.querySelector('[data-item-field="quantity"]');
                quantity.setCustomValidity(quantity.value!=='' && !Number.isInteger(Number(quantity.value))?'La cantidad debe ser un número entero.':'');
                const amount=totals({items:[{...item,tax_amount:''}]}).tax;
                row.querySelector('[data-item-field="tax_amount"]').value=amount.toFixed(2);
            }
            refreshItemTax();
            const search=row.querySelector('[data-product-search]');search.value=productLabel(config.products.find(p=>p.id===item.product_id) || {sku:'',name:''}).replace(/^ · $/,'');
            search.disabled=data().purchase_kind==='service' || draft.review_status==='registered';
            search.addEventListener('change',()=>{item.product_id=config.products.find(p=>productLabel(p)===search.value)?.id || '';data().identity_reviewed=false;changed();});
            const remove=row.querySelector('button');remove.disabled=draft.review_status==='registered';
            remove.addEventListener('click',()=>{data().items.splice(index,1);data().identity_reviewed=false;renderItems();changed();});
            container.append(row);
        });
        paginateItems();
    }
    function renderAdjustments() {
        const container=byId('import-adjustments');container.replaceChildren();
        (data().adjustments || []).forEach((adjustment,index)=>{
            const row=document.createElement('div');row.className='invoice-adjustment';
            row.innerHTML='<select class="form-select" aria-label="Tipo de ajuste"><option value="vat">IVA adicional</option><option value="tax">Otro impuesto</option><option value="charge">Cargo</option><option value="discount">Descuento</option></select><input class="form-control" maxlength="180" aria-label="Descripción del ajuste" placeholder="Descripción"><input class="form-control" type="number" min="0.01" step="0.01" aria-label="Importe del ajuste"><button type="button" class="btn btn-outline-danger icon-btn" title="Quitar ajuste" aria-label="Quitar ajuste"><i class="bi bi-trash"></i></button>';
            const controls=row.querySelectorAll('select,input');
            ['kind','label','amount'].forEach((key,i)=>{controls[i].value=adjustment[key] ?? '';controls[i].disabled=draft.review_status==='registered';controls[i].addEventListener('input',()=>{adjustment[key]=controls[i].value;data().identity_reviewed=false;changed();});});
            const amountField=controls[2];
            if(amountField.value!=='') amountField.value=Number(amountField.value).toFixed(2);
            amountField.addEventListener('change',()=>{
                if(amountField.value!=='' && Number.isFinite(Number(amountField.value))) {
                    amountField.value=Number(amountField.value).toFixed(2);
                    adjustment.amount=amountField.value;
                    data().identity_reviewed=false;changed();
                }
            });
            const remove=row.querySelector('button');remove.disabled=draft.review_status==='registered';remove.addEventListener('click',()=>{data().adjustments.splice(index,1);data().identity_reviewed=false;renderAdjustments();changed();});
            container.append(row);
        });
    }
    function updateTotals() {
        const review=document.querySelector('[data-field="identity_reviewed"]');if(review) review.checked=Boolean(data().identity_reviewed);
        const values=totals(data());for(const key of ['net','tax','extra','total']) byId('import-'+key).textContent=money(values[key]);
        const difference=byId('import-difference');
        difference.textContent=values.difference===null?'Ingresa el total del documento en la revisión final.':values.difference===0?'El total calculado coincide con el documento.':'Diferencia con el documento: '+money(values.difference)+'. Revisa conceptos, impuestos y ajustes.';
        difference.classList.toggle('is-error',values.difference!==null && values.difference!==0);
    }
    function renderSummary() {
        const summary=byId('import-review-summary');summary.replaceChildren();
        for(const [label,value] of [
            ['Proveedor',config.suppliers.find(s=>s.id===data().supplier_id)?.name || 'Falta seleccionar'],
            ['Comprobante',(data().document_type || '')+' '+(data().point_of_sale || '')+'-'+(data().invoice_sequence || '')],
            ['Moneda',data().currency_code || 'Falta seleccionar'],['Conceptos',String(data().items?.length || 0)],
            ['Recepción',config.receipts.find(r=>r.id===data().purchase_receipt_id)?.receipt_number || 'Sin recepción asociada'],
        ]) {const p=document.createElement('p'),strong=document.createElement('strong');strong.textContent=label+': ';p.append(strong,document.createTextNode(value));summary.append(p);}
    }
    async function renderPage() {
        if(!draft) return;
        const epoch=++previewEpoch;byId('import-highlight').hidden=true;
        byId('import-page-label').textContent=page+' / '+draft.document.page_count;
        byId('import-page-prev').disabled=page<=1;byId('import-page-next').disabled=page>=draft.document.page_count;
        if(!pdf) return;
        if(renderTask) {renderTask.cancel();try{await renderTask.promise;}catch{}}
        const documentPage=await pdf.getPage(page);if(epoch!==previewEpoch) return;
        const canvas=byId('import-pdf-canvas'),viewport=documentPage.getViewport({scale:1.5});
        canvas.width=viewport.width;canvas.height=viewport.height;
        renderTask=documentPage.render({canvasContext:canvas.getContext('2d'),viewport});
        try {await renderTask.promise;}catch(error){if(error.name!=='RenderingCancelledException') throw error;}
    }
    async function preview() {
        const previewId=draft.id;const image=byId('import-preview-image'),canvas=byId('import-pdf-canvas');if(pdf) await pdf.destroy();pdf=null;page=1;
        image.hidden=true;canvas.hidden=true;byId('import-highlight').hidden=true;byId('import-original').href=fileUrl();
        if(draft.document.mime_type==='application/pdf') {
            canvas.hidden=false;
            try {
                const pdfjs=await import(config.pdfModule);pdfjs.GlobalWorkerOptions.workerSrc=config.pdfWorker;
                const loaded=await pdfjs.getDocument({url:fileUrl(),withCredentials:true,isEvalSupported:false,useWasm:false,disableFontFace:true,
                    cMapUrl:config.pdfAssets+'cmaps/',cMapPacked:true,standardFontDataUrl:config.pdfAssets+'standard_fonts/'}).promise;
                if(draft.id!==previewId) {await loaded.destroy();return;}
                pdf=loaded;await renderPage();
            } catch(error) {canvas.hidden=true;byId('import-source-note').textContent='No se pudo mostrar la vista previa. Puedes abrir el original con el icono correspondiente.';}
        } else {image.src=fileUrl();image.hidden=false;await renderPage();}
    }
    async function highlight(key) {
        const location=draft?.extraction?.locations?.[key],region=location?.regions?.[0];
        if(!region) {byId('import-highlight').hidden=true;byId('import-source-note').textContent='No hay una ubicación detectada para este dato.';return;}
        if(region.page!==page) {page=region.page;await renderPage();}
        const metadata=draft.extraction.pages?.find(p=>p.number===page),rectangle=highlightRect(region,metadata);
        byId('import-source-note').textContent='Página '+page+' · '+(location.content || 'Ubicación detectada');
        const marker=byId('import-highlight');marker.hidden=!rectangle;
        if(rectangle) {for(const [key,value] of Object.entries(rectangle)) marker.style[key]=value+'%';marker.scrollIntoView({block:'nearest',behavior:'auto'});}
    }
    async function openDraft(id) {
        itemsPage=1;
        const result=await request('/'+encodeURIComponent(id));draft=result.draft;dirty=false;blocked=false;confirmationKey=crypto.randomUUID();
        byId('import-upload-panel').hidden=true;byId('import-workspace').hidden=false;byId('import-filename').textContent=draft.document.original_name;
        renderFields();renderItems();renderAdjustments();processing();setStage(draft.review_status==='registered'?5:3);
        byId('import-save').disabled=draft.review_status==='registered';
        byId('import-confirm').hidden=draft.review_status==='registered';byId('import-success').hidden=draft.review_status!=='registered';
        if(draft.review_status==='registered') byId('import-success').textContent='Factura registrada. El original y los datos revisados quedaron conservados en Compras.';
        await preview();
        if(draft.processing_status==='done' && draft.review_status!=='registered') applyDetectedSuggestions();
        if(config.autoRead && draft.processing_status==='idle' && draft.review_status!=='registered') {
            const queued=await request('/'+draft.id+'/analizar','POST',{});
            draft.processing_status=queued.draft.processing_status;processing();setStage(2);
            message('Lectura solicitada automáticamente. El procesador se ejecuta cada minuto.');
        }
    }
    function applyDetectedSuggestions() {
        if(!draft.extraction?.suggestions || draft.review_status==='registered') return;
        const next=mergeSuggestions(data(),draft.extraction.suggestions);
        const current=JSON.stringify(data());next.identity_reviewed=data().identity_reviewed;
        if(JSON.stringify(next)!==current) {
            next.identity_reviewed=false;draft.data=next;renderFields();renderItems();renderAdjustments();changed();
        }
        message(draft.extraction.notice || 'Datos detectados. Revisa el original antes de registrar.');
        if(stage===2) setStage(3);
    }
    async function upload(file) {
        if(uploading) return;
        if(!file || file.size>config.maxBytes || !/\.(pdf|jpe?g|png)$/i.test(file.name)) {message('Selecciona un PDF, JPG o PNG de hasta 10 MB.',true);return;}
        uploading=true;message('Guardando documento privado…');
        try {
            const body=new FormData();body.append('invoice_file',file);
            const result=await request('','POST',body);
            await openDraft(result.draft.id);
            const address=new URL(window.location.href);address.searchParams.set('import',draft.id);history.replaceState(null,'',address);
            message(result.draft.reused?'Este archivo ya estaba cargado. Abrimos su registro existente.':
                ['queued','reading'].includes(draft.processing_status)?'Documento guardado. Lectura solicitada automáticamente; el procesador se ejecuta cada minuto.':'Documento guardado. Puedes solicitar la lectura o continuar manualmente.');
            if(draft.review_status!=='registered') setStage(draft.processing_status==='done'?3:2);
        } catch(error) {message(error.message,true);} finally {uploading=false;}
    }
    document.querySelectorAll('[data-field]').forEach(control=>{control.addEventListener('input',()=>writeField(control));control.addEventListener('focus',()=>highlight(control.dataset.field));});
    byId('import-form').addEventListener('submit',event=>{event.preventDefault();save().catch(error=>message(error.message,true));});
    byId('invoice-file').addEventListener('change',event=>upload(event.target.files[0]));
    const dropzone=byId('invoice-dropzone');dropzone.addEventListener('click',event=>{if(event.target!==byId('invoice-file')) byId('invoice-file').click();});
    dropzone.addEventListener('keydown',event=>{if(['Enter',' '].includes(event.key)){event.preventDefault();byId('invoice-file').click();}});
    dropzone.addEventListener('dragover',event=>{event.preventDefault();dropzone.classList.add('is-dragging');});
    dropzone.addEventListener('dragleave',()=>dropzone.classList.remove('is-dragging'));
    dropzone.addEventListener('drop',event=>{event.preventDefault();dropzone.classList.remove('is-dragging');if(event.dataTransfer.files.length!==1){message('Sube una factura por archivo y una carga a la vez.',true);return;}upload(event.dataTransfer.files[0]);});
    byId('import-next').addEventListener('click',()=>setStage(stage+1));byId('import-prev').addEventListener('click',()=>setStage(stage-1));
    byId('import-save').addEventListener('click',()=>save().then(()=>message('Borrador guardado.')).catch(error=>message(error.message,true)));
    byId('import-analyze').addEventListener('click',async()=>{try {await save();const result=await request('/'+draft.id+'/analizar','POST',{});draft.processing_status=result.draft.processing_status;processing();message('Lectura solicitada. Puedes avanzar con la revisión mientras se procesa.');}catch(error){message(error.message,true);}});
    byId('import-apply').addEventListener('click',()=>{
        applyDetectedSuggestions();setStage(3);
    });
    byId('import-add-item').addEventListener('click',()=>{if((data().items?.length || 0)>=300){message('Se admiten hasta 300 conceptos.',true);return;}data().items??=[];data().items.push({description:'',quantity:'1',unit_cost:'',tax_rate:'0',tax_amount:'',product_id:'',supplier_code:''});itemsPage=Math.ceil(data().items.length/itemsPageSize);data().identity_reviewed=false;renderItems();changed();});
    byId('import-add-adjustment').addEventListener('click',()=>{data().adjustments??=[];if(data().adjustments.length>=40)return;data().adjustments.push({kind:'charge',label:'',amount:''});data().identity_reviewed=false;renderAdjustments();changed();});
    byId('import-confirm').addEventListener('click',async()=>{
        if(confirming || !draft || draft.review_status==='registered') return;
        confirming=true;byId('import-confirm').disabled=true;byId('import-form').inert=true;
        for(const id of ['import-save','import-analyze','import-apply','import-prev','import-next','import-reload']) byId(id).disabled=true;
        clearTimeout(saveTimer);
        try {
            await save();const values=totals(data());
            if(values.difference===null || values.difference!==0) throw new Error('Completa el total del documento y resuelve la diferencia antes de registrar.');
            if(!data().identity_reviewed) throw new Error('Confirma la revisión del documento antes de registrar.');
            const result=await request('/'+draft.id+'/confirmar','POST',{version:draft.version,request_key:confirmationKey});
            draft=result.draft;dirty=false;renderFields();renderItems();renderAdjustments();processing();
            byId('import-save').disabled=true;byId('import-confirm').hidden=true;byId('import-success').hidden=false;
            byId('import-success').textContent='Factura registrada correctamente. Puedes consultar su cuenta a pagar desde Compras.';
            message('Registro completo. Se conservaron el original y la revisión.');
        } catch(error) {message(error.message,true);} finally {
            confirming=false;byId('import-form').inert=false;byId('import-confirm').disabled=false;
            for(const id of ['import-save','import-apply','import-prev','import-next','import-reload']) byId(id).disabled=false;
            byId('import-save').disabled=draft.review_status==='registered';processing();setStage(stage);
        }
    });
    byId('import-reload').addEventListener('click',async()=>{if(dirty && !window.confirm('Hay cambios sin guardar. Descarga una copia si necesitas conservarlos. ¿Actualizar desde el servidor?'))return;try{await openDraft(draft.id);message('Borrador actualizado.');}catch(error){message(error.message,true);}});
    byId('import-recovery').addEventListener('click',()=>{if(!draft)return;const blob=new Blob([JSON.stringify({id:draft.id,version:draft.version,data:data()},null,2)],{type:'application/json'}),link=document.createElement('a'),objectUrl=URL.createObjectURL(blob);link.href=objectUrl;link.download='borrador-factura-'+draft.id+'.json';link.click();setTimeout(()=>URL.revokeObjectURL(objectUrl),1000);});
    byId('import-page-prev').addEventListener('click',()=>{if(page>1){page--;renderPage().catch(error=>message(error.message,true));}});
    byId('import-page-next').addEventListener('click',()=>{if(page<draft.document.page_count){page++;renderPage().catch(error=>message(error.message,true));}});
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    const selected=new URL(window.location.href).searchParams.get('import');
    if(selected) openDraft(selected).catch(error=>message(error.message,true));
    else setStage(1);
}
