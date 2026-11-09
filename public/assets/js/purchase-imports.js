import {money,totals,mergeSuggestions,highlightRect,importDecimal,normalizeImportItem,stockLine,resumeStage,stepGate} from './purchase-import-utils.mjs?v=circuit-1';
const configNode=document.getElementById('import-config');
if(configNode) {
    const config=JSON.parse(configNode.textContent), byId=id=>document.getElementById(id);
    let draft=null,stage=1,dirty=false,revision=0,savePromise=null,saveTimer=null,pollTimer=null,blocked=false;
    let circuit=null,circuitBusy=false,circuitTicket=0;
    let csrf=config.csrfToken,confirmationKey='',uploading=false,confirming=false,pdf=null,page=1,renderTask=null,previewEpoch=0;
    const data=()=>draft?.data || {};
    const url=(suffix='')=>{const value=new URL(config.baseUrl+suffix,window.location.href);value.searchParams.set('company_id',config.companyId);return value;};
    const fileUrl=()=>url('/'+encodeURIComponent(draft.id)+'/archivo').href;
    function message(text,error=false) {
        for(const id of ['import-message','import-inline-message']) {
            const node=byId(id);if(!node) continue;
            node.textContent=text;node.hidden=!text;node.classList.toggle('is-error',error);
        }
        if(error) byId('import-inline-message')?.scrollIntoView({block:'center',behavior:'smooth'});
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
    const isGoods=()=>(data().purchase_kind || 'goods')==='goods';
    const isRegistered=()=>draft?.review_status==='registered';
    const linesLocked=()=>isRegistered() || Boolean(draft?.purchase_order_id || draft?.purchase_receipt_id);
    function markSteps() {
        const steps=circuit?.steps || {};
        document.querySelectorAll('[data-import-step]').forEach(node=>{
            const step=Number(node.dataset.importStep);
            if(step===stage) node.setAttribute('aria-current','step');else node.removeAttribute('aria-current');
            node.classList.toggle('is-skipped',Boolean(draft) && !isGoods() && step>=5 && step<=7);
            node.classList.toggle('is-done',(step===5 && steps.order) || (step===6 && steps.approval) || (step===7 && steps.receipt) || (step===8 && (steps.invoice || isRegistered())));
        });
    }
    function setStage(next) {
        stage=Math.max(draft?2:1,Math.min(8,next));
        const panels={2:'import-reading-panel',3:'import-header-panel',4:'import-link-panel',5:'import-order-panel',6:'import-approve-panel',7:'import-receipt-panel',8:'import-confirm-panel'};
        for(const [number,id] of Object.entries(panels)) byId(id).hidden=stage!==Number(number);
        byId('import-prev').disabled=stage<=2;byId('import-next').hidden=stage>=8;
        byId('import-stage-title').textContent=({2:'Leer el documento',3:'Datos de la factura',4:'Productos y destino de la compra',5:'Orden de compra',6:'Aprobación de la orden',7:'Recepción e ingreso al stock',8:'Factura: revisión final'})[stage] || 'Subir factura';
        byId('import-stage-caption').textContent=stage>=5 && stage<=7?'CIRCUITO DE COMPRA':'REVISIÓN ASISTIDA';
        byId('import-stage-help').textContent=stage===2?'Puedes continuar aunque la lectura esté pendiente.':stage>=5 && stage<=7?'Cada documento se confirma por separado.':stage===8?'El registro se confirma una sola vez.':'Los cambios se guardan como borrador.';
        markSteps();updateTotals();
        if(stage>=5) {renderCircuit();loadCircuit();}
        if(stage===8) renderSummary();
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
    function processing(showMessage=true) {
        if(!draft) return;
        const names={idle:'Documento pendiente de lectura',queued:'Lectura solicitada · el procesador se ejecuta cada minuto',reading:'Leyendo documento',done:'Lectura disponible para revisar',error:'Lectura interrumpida: puedes continuar manualmente'};
        byId('import-processing').textContent=names[draft.processing_status] || '';
        byId('import-apply').hidden=!draft.extraction?.suggestions || draft.review_status==='registered';
        byId('import-analyze').disabled=!config.ocrConfigured || ['queued','reading'].includes(draft.processing_status) || draft.review_status==='registered';
        if(showMessage && draft.last_error) message(draft.last_error,true);
        else if(showMessage && draft.processing_status==='done' && draft.extraction?.notice) message(draft.extraction.notice);
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
        if(key==='purchase_kind' && control.value!=='goods') data().purchase_receipt_id='';
        if(key==='purchase_kind' && control.value==='service') {for(const item of data().items || []) item.product_id='';renderItems();}
        if(key==='purchase_kind') {circuit=null;markSteps();}
        changed();
    }
    function renderFields() {
        document.querySelectorAll('[data-field]').forEach(control=>{
            const value=data()[control.dataset.field];
            if(control.type==='checkbox') control.checked=Boolean(value);else control.value=String(value ?? '').slice(0,control.type==='date'?10:10000);
            // Supplier, currency and destination are fixed once the purchase order exists.
            control.disabled=isRegistered() || (['purchase_kind','supplier_id','currency_code'].includes(control.dataset.field) && linesLocked());
        });renderConfidence();
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
            const previous=JSON.stringify(item);
            if(draft.review_status!=='registered') {
                Object.assign(item,normalizeImportItem(item));
            }
            const row=document.createElement('div');row.className='invoice-item';
            row.innerHTML='<div class="invoice-item-head"><label class="invoice-field"><span>Descripción</span><input class="form-control" data-item-field="description" maxlength="500"></label><button type="button" class="btn btn-outline-danger icon-btn" title="Quitar concepto" aria-label="Quitar concepto"><i class="bi bi-trash"></i></button></div><div class="invoice-item-numbers"><label class="invoice-field"><span>Cantidad</span><input class="form-control" data-item-field="quantity" type="number" min="0.000001" step="0.000001"></label><label class="invoice-field"><span>Costo neto unitario</span><input class="form-control" data-item-field="unit_cost" type="number" min="0" step="0.000001"></label><label class="invoice-field"><span>IVA %</span><input class="form-control" data-item-field="tax_rate" type="number" min="0" max="100" step="0.0001"></label><label class="invoice-field"><span>Importe IVA</span><input class="form-control" data-item-field="tax_amount" type="number" min="0" step="0.01" placeholder="Calculado"></label></div><div class="invoice-item-link"><label class="invoice-field"><span>Producto interno</span><input class="form-control" data-product-search list="import-products" placeholder="Busca por código o nombre"></label><label class="invoice-field"><span>Código del proveedor</span><input class="form-control" data-item-field="supplier_code" maxlength="120"></label><label class="invoice-field"><span>Unidades por bulto</span><input class="form-control" data-item-field="units_per_package" type="number" min="0.0001" step="any" placeholder="1"></label><p class="invoice-item-stock" data-item-stock></p></div>';
            const locked=linesLocked();
            const refreshStock=()=>{
                const line=stockLine(item),note=row.querySelector('[data-item-stock]');
                note.textContent=!line.valid?'Las unidades por bulto deben ser mayores que cero.':line.units===1?'Ingresa al stock como '+line.quantity.toLocaleString('es-AR')+' unidades.'
                    :line.packages.toLocaleString('es-AR')+' bultos × '+line.units.toLocaleString('es-AR')+' = '+line.quantity.toLocaleString('es-AR')+' unidades de stock a $ '+line.unitCost.toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:4})+' cada una.';
                note.hidden=data().purchase_kind==='service';
            };
            row.querySelectorAll('[data-item-field]').forEach(control=>{
                const key=control.dataset.itemField;control.value=item[key] ?? '';control.disabled=locked;
                if(key==='units_per_package') {
                    control.closest('label').hidden=data().purchase_kind==='service';
                    control.addEventListener('input',()=>{item.units_per_package=control.value;refreshStock();data().identity_reviewed=false;changed();});
                    return;
                }
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
                            control.value=key==='quantity'?String(Number(control.value)):importDecimal(control.value);
                            item[key]=control.value;
                            refreshItemTax();changed();
                        }
                    });
                }
                control.addEventListener('focus',()=>highlight('items.'+index+'.'+key));
                control.addEventListener('input',()=>{
                    item[key]=control.value;if(['quantity','unit_cost','tax_rate'].includes(key)) {item.tax_amount='';refreshItemTax();refreshStock();}
                    data().identity_reviewed=false;changed();
                });
            });
            refreshStock();
            function refreshItemTax() {
                const quantity=row.querySelector('[data-item-field="quantity"]');
                quantity.setCustomValidity(quantity.value!=='' && !Number.isInteger(Number(quantity.value))?'La cantidad debe ser un número entero.':'');
                const amount=totals({items:[{...item,tax_amount:''}]}).tax;
                row.querySelector('[data-item-field="tax_amount"]').value=amount.toFixed(2);
            }
            refreshItemTax();
            const search=row.querySelector('[data-product-search]');search.value=productLabel(config.products.find(p=>p.id===item.product_id) || {sku:'',name:''}).replace(/^ · $/,'');
            search.disabled=data().purchase_kind==='service' || locked;
            search.addEventListener('change',()=>{item.product_id=config.products.find(p=>productLabel(p)===search.value)?.id || '';data().identity_reviewed=false;changed();});
            const remove=row.querySelector('button');remove.disabled=locked;
            remove.addEventListener('click',()=>{data().items.splice(index,1);data().identity_reviewed=false;renderItems();changed();});
            container.append(row);
            if(JSON.stringify(item)!==previous && !locked) changed();
        });
        byId('import-add-item').disabled=linesLocked();
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
            ['Orden de compra',!isGoods()?'No aplica':circuit?.order?.order_number || (circuit?.receipt?'Recepción registrada desde Compras':'Pendiente (paso 5)')],
            ['Recepción',!isGoods()?'No aplica: '+(data().purchase_kind==='service'?'servicios o gastos':'mercadería ya ingresada al stock'):circuit?.receipt?.receipt_number || 'Pendiente (paso 7)'],
        ]) {const p=document.createElement('p'),strong=document.createElement('strong');strong.textContent=label+': ';p.append(strong,document.createTextNode(value));summary.append(p);}
    }
    // ---- Purchase circuit (steps 5-7): every document is generated by the server with the Compras rules. ----
    function el(tag,props={},...children) {
        const node=document.createElement(tag);
        for(const [key,value] of Object.entries(props)) {
            if(key==='class') node.className=value;else if(key==='text') node.textContent=value;
            else if(key.startsWith('on')) node.addEventListener(key.slice(2),value);
            else if(value!==false && value!==null && value!==undefined) node.setAttribute(key,value===true?'':String(value));
        }
        for(const child of children.flat()) if(child!==null && child!==undefined && child!==false) node.append(child instanceof Node?child:document.createTextNode(String(child)));
        return node;
    }
    const quantityText=value=>(Number(value)||0).toLocaleString('es-AR',{maximumFractionDigits:2});
    const costText=value=>'$ '+(Number(value)||0).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:4});
    const productText=id=>{const product=config.products.find(p=>p.id===id);return product?productLabel(product):'Producto no disponible';};
    const orderStatus={draft:'Borrador',approved:'Aprobada',received_partial:'Recibida parcialmente',received_total:'Recibida',cancelled:'Anulada'};
    const skipBox=text=>el('div',{class:'invoice-circuit-skip'},text);
    const doneBox=(title,detail)=>el('div',{class:'invoice-circuit-done',role:'status'},el('i',{class:'bi bi-check-circle','aria-hidden':'true'}),el('div',{},el('strong',{text:title}),detail?el('div',{class:'small mt-1',text:detail}):null));
    function table(headers,rows,numeric=[]) {
        return el('table',{class:'invoice-circuit-table'},el('thead',{},el('tr',{},headers.map((header,i)=>el('th',{class:numeric[i]?'num':null,text:header})))),
            el('tbody',{},rows.map(cells=>el('tr',{},cells.map((cell,i)=>el('td',{class:numeric[i]?'num':null},cell))))));
    }
    const actionButton=(icon,label,onclick)=>el('button',{type:'button',class:'btn btn-dark',disabled:circuitBusy || isRegistered(),onclick},el('i',{class:'bi '+icon+' me-1','aria-hidden':'true'}),label);
    const invoiceNumber=()=>[data().point_of_sale,data().invoice_sequence].filter(Boolean).join('-') || data().invoice_number || '';
    async function loadCircuit() {
        if(!draft) return;
        const id=draft.id,ticket=++circuitTicket;
        try {
            if(dirty && !blocked) await save();
            const result=await request('/'+encodeURIComponent(id)+'/circuito');
            if(!draft || draft.id!==id || ticket!==circuitTicket) return;
            circuit=result.circuit;markSteps();renderCircuit();if(stage===8) renderSummary();
        } catch(error) {message(error.message,true);}
    }
    async function circuitAction(path,body,doneMessage) {
        if(circuitBusy || !draft) return false;
        circuitBusy=true;renderCircuit();
        try {
            clearTimeout(saveTimer);await save();
            const result=await request('/'+encodeURIComponent(draft.id)+path,'POST',{version:draft.version,...body});
            const remote=result.draft;
            draft.version=remote.version;draft.review_status=remote.review_status;
            draft.purchase_order_id=remote.purchase_order_id;draft.purchase_receipt_id=remote.purchase_receipt_id;
            data().purchase_kind=remote.data.purchase_kind ?? data().purchase_kind;data().purchase_receipt_id=remote.data.purchase_receipt_id ?? '';
            data().items=remote.data.items;
            circuit=result.circuit;renderFields();renderItems();markSteps();message(doneMessage);
            return true;
        } catch(error) {message(error.message,true);return false;}
        finally {circuitBusy=false;renderCircuit();}
    }
    function renderCircuit() {
        if(!draft) return;
        renderOrderPanel();renderApprovePanel();renderReceiptPanel();
    }
    function renderOrderPanel() {
        const body=byId('import-order-body');body.replaceChildren();
        if(!isGoods()) {body.append(skipBox('No aplica: esta factura no ingresa mercadería al stock. Continúa con el registro de la factura.'));return;}
        if(!circuit) {body.append(el('p',{class:'invoice-help',text:'Consultando el estado de la compra…'}));return;}
        const order=circuit.order;
        if(!order && circuit.receipt) {body.append(doneBox('Factura vinculada a la recepción '+circuit.receipt.receipt_number,'La mercadería ya había ingresado al stock desde Compras; no se genera una orden nueva.'));return;}
        if(order) {
            body.append(doneBox('Orden de compra '+order.order_number,'Estado: '+(orderStatus[order.status] || order.status)+' · Depósito: '+(order.warehouse_name || '-')),
                table(['Producto','Cantidad','Costo unitario'],order.lines.map(line=>[productLabel({sku:line.sku,name:line.product_name}),quantityText(line.quantity),costText(line.unit_cost)]),[false,true,true]),
                el('p',{class:'invoice-help',text:'Los conceptos, el proveedor y la moneda de la factura quedan fijos para que coincidan con la orden y la recepción.'}));
            return;
        }
        body.append(el('h3',{text:'Generar la orden de compra'}),
            el('p',{class:'invoice-help',text:'Se crea con los productos de la factura convertidos a unidades de stock. Queda en borrador hasta aprobarla en el paso siguiente; todavía no mueve el stock.'}));
        if(circuit.preview_error) {body.append(el('div',{class:'invoice-message is-error',text:circuit.preview_error+' Vuelve al paso 4 para completarlo.'}));return;}
        body.append(table(['Producto','Bultos × unidades','Unidades de stock','Costo por unidad'],
            circuit.preview.map(line=>[productText(line.product_id),quantityText(line.packages)+' × '+quantityText(line.units_per_package),quantityText(line.quantity),costText(line.unit_cost)]),[false,true,true,true]));
        const warehouse=el('select',{class:'form-select','aria-label':'Depósito destino'},circuit.warehouses.map(w=>el('option',{value:w.id,selected:Number(w.is_default)===1},w.name)));
        const warehouseField=el('label',{class:'invoice-field'},el('span',{text:'Depósito donde ingresa la mercadería'}),warehouse);
        body.append(warehouseField);
        if(circuit.pending_orders.length) {
            const choice=(value,label,checked)=>el('label',{},el('input',{type:'radio',name:'import-order-source',value,checked,onchange:()=>{warehouseField.hidden=Boolean(value);}}),el('span',{text:label}));
            body.append(el('p',{class:'invoice-help mt-3',text:'El proveedor tiene órdenes aprobadas con cantidades pendientes de recibir. Puedes recibir contra una de ellas en lugar de crear una nueva.'}),
                el('div',{class:'invoice-circuit-options',role:'radiogroup','aria-label':'Origen de la orden'},choice('','Crear una orden nueva desde esta factura',true),
                    circuit.pending_orders.map(o=>choice(o.id,'Usar la orden '+o.order_number+' ('+o.lines.length+' renglones pendientes de recibir)',false))));
        }
        body.append(el('div',{class:'invoice-circuit-actions'},actionButton('bi-file-earmark-plus','Generar orden de compra',async()=>{
            const source=body.querySelector('input[name="import-order-source"]:checked')?.value || '';
            if(!source && !warehouse.value) {message('Selecciona el depósito donde ingresa la mercadería.',true);return;}
            const ok=await circuitAction('/orden',source?{order_id:source}:{warehouse_id:warehouse.value},source?'Orden vinculada. Continúa con la aprobación.':'Orden de compra generada. Continúa con la aprobación.');
            if(ok) setStage(circuit?.steps?.approval?7:6);
        })));
        if(circuit.receipts.length) {
            const receipt=el('select',{class:'form-select','aria-label':'Recepción registrada'},circuit.receipts.map(r=>el('option',{value:r.id},r.receipt_number+' · '+String(r.received_at || '').slice(0,10))));
            body.append(el('details',{},el('summary',{text:'¿Ya recepcionaste esta mercadería desde Compras?'}),
                el('p',{class:'invoice-help mt-2',text:'Vincula la factura con esa recepción. No se crea una orden nueva y el stock no se vuelve a sumar.'}),receipt,
                el('div',{class:'invoice-circuit-actions'},el('button',{type:'button',class:'btn btn-outline-dark',disabled:circuitBusy || isRegistered(),onclick:async()=>{
                    const ok=await circuitAction('/recepcion/vincular',{receipt_id:receipt.value},'Factura vinculada a la recepción existente.');
                    if(ok) setStage(8);
                }},el('i',{class:'bi bi-link-45deg me-1','aria-hidden':'true'}),'Vincular recepción'))));
        }
    }
    function renderApprovePanel() {
        const body=byId('import-approve-body');body.replaceChildren();
        if(!isGoods()) {body.append(skipBox('No aplica: esta factura no genera orden de compra.'));return;}
        if(!circuit) {body.append(el('p',{class:'invoice-help',text:'Consultando el estado de la compra…'}));return;}
        const order=circuit.order;
        if(!order && circuit.receipt) {body.append(skipBox('No aplica: la factura se vinculó a una recepción ya registrada en Compras.'));return;}
        if(!order) {body.append(skipBox('Primero genera la orden de compra en el paso anterior.'));return;}
        if(order.status!=='draft') {body.append(doneBox('Orden '+order.order_number+' aprobada','Ya se puede recibir la mercadería en '+(order.warehouse_name || 'el depósito de la orden')+'.'));return;}
        body.append(el('h3',{text:'Aprobar la orden '+order.order_number}),
            el('p',{class:'invoice-help',text:'La aprobación habilita la recepción. Revisa proveedor, depósito, cantidades y costos antes de aprobar.'}),
            table(['Producto','Cantidad','Costo unitario'],order.lines.map(line=>[productLabel({sku:line.sku,name:line.product_name}),quantityText(line.quantity),costText(line.unit_cost)]),[false,true,true]),
            el('p',{class:'small'},el('strong',{text:'Depósito: '}),order.warehouse_name || '-',' · ',el('strong',{text:'Total de la orden: '}),'$ '+money(order.total)),
            el('div',{class:'invoice-circuit-actions'},actionButton('bi-check2-circle','Aprobar orden de compra',async()=>{
                if(await circuitAction('/orden/aprobar',{},'Orden aprobada. Continúa con la recepción.')) setStage(7);
            })));
    }
    function renderReceiptPanel() {
        const body=byId('import-receipt-body');body.replaceChildren();
        if(!isGoods()) {body.append(skipBox('No aplica: esta factura no ingresa mercadería al stock.'));return;}
        if(!circuit) {body.append(el('p',{class:'invoice-help',text:'Consultando el estado de la compra…'}));return;}
        const order=circuit.order,receipt=circuit.receipt;
        if(receipt) {
            const payable=receipt.payable;
            body.append(doneBox('Recepción '+receipt.receipt_number+' registrada: la mercadería ingresó al stock',
                (order?'Depósito: '+(order.warehouse_name || '-')+'. ':'')+(payable?'Cuenta a pagar '+payable.payable_number+' por $ '+money(payable.total_amount)+'; al registrar la factura se ajusta a su importe real.':'')));
            return;
        }
        if(!order) {body.append(skipBox('Primero genera la orden de compra.'));return;}
        if(order.status==='draft') {body.append(skipBox('Aprueba la orden '+order.order_number+' para registrar la recepción.'));return;}
        const pending=order.lines.filter(line=>Number(line.pending_quantity)>0);
        body.append(el('h3',{text:'Recibir la mercadería'}),
            el('p',{class:'invoice-help',text:'Indica lo que efectivamente llegó. Al registrar, se suma el stock en '+(order.warehouse_name || 'el depósito de la orden')+', se crea la capa de costo, la cuenta a pagar y el asiento contable. Si recibes menos de lo facturado, la factura podrá registrarse cuando llegue el resto.'}));
        const inputs=pending.map(line=>{
            const quantity=el('input',{class:'form-control form-control-sm',type:'number',min:'0',max:String(line.pending_quantity),step:'any',value:String(Number(line.pending_quantity)),'aria-label':'Cantidad recibida de '+line.product_name});
            const lot=Number(line.lot_control)===1?el('input',{class:'form-control form-control-sm',maxlength:'80','aria-label':'Lote de '+line.product_name}):null;
            const expiration=Number(line.expiration_control)===1 || Number(line.lot_control)===1?el('input',{class:'form-control form-control-sm',type:'date','aria-label':'Vencimiento de '+line.product_name}):null;
            const serial=Number(line.serial_control)===1?el('input',{class:'form-control form-control-sm',maxlength:'80','aria-label':'Serie de '+line.product_name}):null;
            return {line,quantity,lot,expiration,serial};
        });
        body.append(table(['Producto','Pendiente','Recibido','Lote','Vence','Serie'],inputs.map(({line,quantity,lot,expiration,serial})=>[
            productLabel({sku:line.sku,name:line.product_name}),quantityText(line.pending_quantity),quantity,lot || '—',expiration || '—',serial || '—']),[false,true,true]));
        const supplierDocument=el('input',{class:'form-control',maxlength:'80',value:invoiceNumber()});
        body.append(el('label',{class:'invoice-field'},el('span',{text:'Remito o comprobante del proveedor'}),supplierDocument),
            el('div',{class:'invoice-circuit-actions'},actionButton('bi-box-arrow-in-down','Registrar recepción e ingresar al stock',async()=>{
                const lines=inputs.map(({line,quantity,lot,expiration,serial})=>({purchase_order_item_id:line.id,quantity:quantity.value || '0',
                    lot_number:lot?.value || '',expiration_date:expiration?.value || '',serial_number:serial?.value || ''}));
                if(!lines.some(line=>Number(line.quantity)>0)) {message('Indica al menos una cantidad recibida.',true);return;}
                if(await circuitAction('/recepcion',{lines,supplier_document:supplierDocument.value},'Recepción registrada: la mercadería ingresó al stock. Continúa con el registro de la factura.')) setStage(8);
            })));
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
        renderFields();renderItems();renderAdjustments();processing();
        // Resume on the first purchase document still missing (order, approval, receipt or invoice).
        circuit=null;
        try {circuit=(await request('/'+encodeURIComponent(id)+'/circuito')).circuit;} catch {circuit=null;}
        setStage(resumeStage(draft,circuit));
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
    byId('import-next').addEventListener('click',async()=>{
        if(stage===4) {
            try {await save();} catch(error) {message(error.message,true);return;}
            setStage(isGoods()?5:8);return;
        }
        const gate=stepGate(stage,data().purchase_kind,circuit);
        if(!gate.ok) {message(gate.reason,true);return;}
        setStage(stage+1);
    });
    byId('import-prev').addEventListener('click',()=>setStage(stage===8 && !isGoods()?4:stage-1));
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
            if(isGoods() && !data().purchase_receipt_id) throw new Error('Antes de registrar la factura completa la orden de compra, su aprobación y la recepción (pasos 5 a 7), o indica en el paso 4 que la mercadería ya ingresó al stock.');
            const invalidQuantity=(data().items || []).findIndex(item=>!Number.isInteger(Number(item.quantity)) || Number(item.quantity)<=0);
            if(invalidQuantity!==-1) throw new Error('El concepto '+(invalidQuantity+1)+' requiere una cantidad entera mayor que cero.');
            if(values.difference===null || values.difference!==0) throw new Error('Completa el total del documento y resuelve la diferencia antes de registrar.');
            if(!data().identity_reviewed) throw new Error('Confirma la revisión del documento antes de registrar.');
            const result=await request('/'+draft.id+'/confirmar','POST',{version:draft.version,request_key:confirmationKey});
            draft=result.draft;dirty=false;renderFields();renderItems();renderAdjustments();processing();
            byId('import-save').disabled=true;byId('import-confirm').hidden=true;byId('import-success').hidden=false;
            byId('import-success').replaceChildren(
                el('strong',{text:circuit?.order?'Compra completa: orden '+circuit.order.order_number+', recepción '+(circuit.receipt?.receipt_number || '')+' y factura registradas.':'Factura registrada correctamente.'}),
                el('div',{class:'mt-1'},'La cuenta a pagar quedó con el importe de la factura. ',el('a',{href:config.purchasesUrl+'?company_id='+encodeURIComponent(config.companyId),text:'Ir a Compras para pagarla'}),'.'));
            loadCircuit();
            message('Registro completo. Se conservaron el original y la revisión.');
        } catch(error) {message(error.message,true);} finally {
            confirming=false;byId('import-form').inert=false;byId('import-confirm').disabled=false;
            for(const id of ['import-save','import-apply','import-prev','import-next','import-reload']) byId(id).disabled=false;
            byId('import-save').disabled=draft.review_status==='registered';processing(false);setStage(stage);
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
