window.createKioskPayments = function (container, methods, getBase, onChange, options = {}) {
    const money = value => (Number(value)||0).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
    const cents = value => Math.round((Number(value)||0)*100);
    const parse = value => Array.isArray(value) ? value : (value ? JSON.parse(value) : []);
    const rows = [];
    if (options.compact) container.classList.add('company-payments', 'company-payments--compact');
    const methodLabel = (catalog,id) => catalog.find(method=>method.id===id)?.code || id;
    const context = () => options.context?.() || {};
    const eligible = method => {
        if (Number(method.allow_collections ?? 1) !== 1) return false;
        const ctx = context();
        return ['cash','card','transfer','check','wallet'].includes(method.type)
            && (!parse(method.currency_ids).length || (method.currency_codes || []).includes(ctx.currency_code))
            && (!parse(method.branch_ids).length || parse(method.branch_ids).includes(ctx.branch_id))
            && (!parse(method.point_of_sale_ids).length || parse(method.point_of_sale_ids).includes(ctx.point_of_sale_id));
    };
    const data = () => rows.map(row => {
        const method = methods.find(item=>item.id===row.select.value);
        const original = row.original;
        const base = cents(row.base.value);
        const originalBase = original ? cents(Number(original.base_amount)>0 ? original.base_amount : Number(original.amount)-Number(original.surcharge_amount||0)) : 0;
        const preserved = original?.id && row.select.value === (original.payment_method_id || 'legacy:'+original.id) && base === originalBase;
        const snapshot = preserved && original.payment_method_snapshot ? JSON.parse(original.payment_method_snapshot) : null;
        const rate = preserved ? Number(original.surcharge_rate||0) : (options.collection ? 0 : Number(method?.percentage||0));
        const surcharge = preserved ? cents(original.surcharge_amount||0) : Math.round(base*Math.round(rate*100)/10000);
        const total = preserved ? cents(original.amount) : base+surcharge;
        const pending = preserved ? original.status === 'pending' : method?.type === 'transfer' || Boolean(Number(method?.requires_confirmation||0));
        return {id:preserved ? (original.payment_method_id||'') : (method?.id||''), saved_payment_id:original?.id||'',
            code:preserved ? original.payment_method_code||original.payment_method : method?.code||'', type:preserved ? original.payment_method : (method?.type==='wallet'?'qr':method?.type),
            show_on_receipt:Number(snapshot?.show_on_receipt ?? method?.show_on_receipt ?? 1), pending,
            rate, base:base/100, surcharge:surcharge/100, total:total/100,
            received:preserved && Number(original.received_amount)>0 ? Number(original.received_amount) : total/100,
            change:preserved ? Number(original.change_amount||0) : 0, reference:row.reference.value, external_reference:original?.external_reference||'', gateway_id:row.gateway.value, cash_check_id:row.check.value,
            paid_at:row.date.value, notes:row.notes.value, fields:{...(preserved ? parse(original.payment_data) : {}),...Object.fromEntries([...row.fields.querySelectorAll('input')].map(input=>[input.dataset.field,input.value]))},
            allowed:preserved || (method && eligible(method))};
    });
    const canAdd = () => rows.length<20 && !data().some(line=>!line.id && !line.saved_payment_id)
        && (options.collection || cents(getBase())>data().reduce((sum,line)=>sum+cents(line.base),0));
    const update = () => {
        const values=data();
        rows.forEach((row,i)=>{
            const value=values[i]; row.fee.textContent=value.rate ? 'Recargo '+money(value.rate)+' %: '+money(value.surcharge) : '';
            row.total.textContent=money(value.total); row.remove.disabled=!options.allowEmpty && rows.length===1;
            row.status.textContent=value.saved_payment_id && !value.id ? 'Pago histórico: conserva su tipo original.' : value.pending ? 'Pendiente de verificación' : '';
            [...row.select.options].forEach(option=>{if(!option.value)return; const method=methods.find(item=>item.id===option.value); option.disabled=method ? !eligible(method) && option.value!==(row.original?.payment_method_id||'') : option.value!==(row.original?.payment_method_id||'legacy:'+row.original?.id);});
        });
        onChange();
    };
    const add = (initial = null) => {
        if (rows.length>=20 || (!initial && rows.length && !canAdd())) return;
        const remaining=Math.max(0,cents(getBase())-data().reduce((sum,line)=>sum+cents(line.base),0))/100;
        const element=document.createElement('div'); element.className='border-bottom py-3';
        element.innerHTML='<div class="row g-2 align-items-end"><label class="col-md-5">Medio de pago<select class="form-select"></select></label><label class="col-md-3">'+(options.collection?'Importe del cobro':'Importe a cubrir')+'<input class="form-control base" type="number" min="0.01" step="0.01"></label><div class="col"><strong class="total"></strong><div class="fee small text-secondary"></div></div><div class="col-auto"><button type="button" class="btn btn-outline-danger icon-btn remove" title="Eliminar pago" aria-label="Eliminar pago"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div><div class="status small text-secondary my-1"></div><div class="row g-2 mt-1"><label class="col-md-4">Referencia<input class="reference form-control" maxlength="120"></label><label class="col-md-4">Fecha<input class="date form-control" type="datetime-local"></label><label class="col-md-4">Nota<input class="notes form-control" maxlength="500"></label></div><div class="row g-2 fields mt-1"></div>';
        if (options.compact) {
            element.className='payment-line';
            element.innerHTML='<div class="payment-line-main"><label class="payment-method">Medio de pago<select class="form-select"></select></label><label class="payment-amount">Importe a cubrir<input class="form-control base" type="number" min="0.01" step="0.01"></label><div class="payment-result"><span class="payment-result-label">A cobrar con este medio</span><strong class="total"></strong><span class="fee small text-secondary"></span><span class="status small text-secondary"></span></div><div class="payment-actions"><button type="button" class="btn btn-outline-dark icon-btn details-toggle" title="Completar datos del medio" aria-label="Completar datos del medio" hidden><i class="bi bi-input-cursor-text" aria-hidden="true"></i></button><button type="button" class="btn btn-outline-danger icon-btn remove" title="Eliminar pago" aria-label="Eliminar pago"><i class="bi bi-trash" aria-hidden="true"></i></button></div></div><dialog class="payment-details" aria-label="Datos del medio de pago"><div class="payment-details-head"><h3 class="h5 mb-0">Datos del medio de pago</h3><button type="button" class="btn btn-outline-dark icon-btn details-close" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="payment-details-body"><label class="payment-reference">Referencia<input class="reference form-control" maxlength="120"></label><label class="payment-date">Fecha<input class="date form-control" type="datetime-local"></label><input class="notes" type="hidden"><div class="fields payment-detail-fields"></div></div><div class="payment-details-footer"><button type="button" class="btn btn-dark icon-btn details-accept" title="Aceptar datos" aria-label="Aceptar datos"><i class="bi bi-check-lg" aria-hidden="true"></i></button></div></dialog>';
        }
        const row={element,select:element.querySelector('select'),original:initial,requiredControls:[]};
        for(const key of ['base','fee','total','remove','status','reference','date','notes','fields'])row[key]=element.querySelector('.'+key);
        row.gateway=document.createElement('select'); row.check=document.createElement('select');
        for(const [key,label,items,labelKey] of [['gateway','Pasarela',options.gateways||[],'name'],['check','Cheque',options.checks||[],'check_number']]) {
            row[key].className='form-select'; row[key].add(new Option('Sin seleccionar',''));
            items.forEach(item=>row[key].add(new Option(item[labelKey],item.id)));
            if(items.length || options.compact){const holder=document.createElement('label');holder.className=options.compact?'payment-detail-field':'col-md-4';holder.textContent=label;holder.append(row[key]);row.fields.before(holder);row[key+'Holder']=holder;}
        }
        row.select.add(new Option('Seleccionar medio de pago',''));
        methods.forEach(method=>row.select.add(new Option(method.code+' · '+method.name,method.id)));
        if(initial) {
            const key=initial.payment_method_id||'legacy:'+initial.id;
            if(![...row.select.options].some(option=>option.value===key))row.select.add(new Option((initial.payment_method_name||initial.payment_method_code||initial.payment_method)+' (guardado)',key));
            const savedOption=[...row.select.options].find(option=>option.value===key);
            if(savedOption && initial.id) savedOption.textContent=(initial.payment_method_code||methodLabel(methods,key))+' · '+(initial.payment_method_name||initial.payment_method||'Pago')+' (guardado)';
            row.select.value=key;row.base.value=(Number(initial.base_amount)>0?Number(initial.base_amount):Number(initial.amount)-Number(initial.surcharge_amount||0)).toFixed(2);
            row.reference.value=initial.reference||''; row.notes.value=initial.notes||'';row.date.value=(initial.paid_at||'').replace(' ','T').slice(0,16);
            if(initial.gateway_id && ![...row.gateway.options].some(o=>o.value===initial.gateway_id))row.gateway.add(new Option('Pasarela guardada',initial.gateway_id));
            if(initial.cash_check_id && ![...row.check.options].some(o=>o.value===initial.cash_check_id))row.check.add(new Option('Cheque guardado',initial.cash_check_id));
            row.gateway.value=initial.gateway_id||'';row.check.value=initial.cash_check_id||'';
        } else row.base.value=options.partial||options.collection ? '' : remaining.toFixed(2);
        if (options.compact) {
            row.details=element.querySelector('.payment-details');
            row.detailsButton=element.querySelector('.details-toggle');
            row.openDetails=()=>{
                if (!row.details.open) row.details.showModal();
                row.requiredControls.forEach(control=>{control.required=true;});
                (row.requiredControls.find(control=>!String(control.value||'').trim()) || row.requiredControls[0] || row.reference).focus();
            };
            const close=()=>row.details.close();
            const accept=()=>{
                const invalid=row.requiredControls.find(control=>!control.checkValidity());
                if (invalid) { invalid.reportValidity(); return; }
                close();
            };
            row.detailsButton.addEventListener('click',row.openDetails);
            element.querySelector('.details-close').addEventListener('click',close);
            element.querySelector('.details-accept').addEventListener('click',accept);
            row.details.addEventListener('close',()=>{row.requiredControls.forEach(control=>{control.required=false;});update();});
            row.details.addEventListener('keydown',event=>{
                if(event.key==='Enter' && !['TEXTAREA','BUTTON'].includes(event.target.tagName)) {event.preventDefault();accept();}
            });
        }
        let fieldValues=initial?.payment_data ? JSON.parse(initial.payment_data):{};
        const fields=()=>{
            row.reference.required=false;row.date.required=false;row.gateway.required=false;row.check.required=false;
            let method=methods.find(item=>item.id===row.select.value);
            const originalBase=initial ? cents(Number(initial.base_amount)>0?initial.base_amount:Number(initial.amount)-Number(initial.surcharge_amount||0)):0;
            if (initial?.id && row.select.value===initial.payment_method_id && cents(row.base.value)===originalBase && initial.payment_method_snapshot) method=JSON.parse(initial.payment_method_snapshot);
            row.fields.querySelectorAll('input').forEach(input=>{fieldValues[input.dataset.field]=input.value;});
            const values=fieldValues;
            row.fields.replaceChildren();
            parse(method?.required_fields).forEach(field=>{
                if(['reference','referencia','external_reference'].includes(field)){row.reference.required=!initial?.external_reference || Boolean(row.reference.value);return;}
                if(['paid_at','fecha'].includes(field)){row.date.required=true;return;}
                if(field==='gateway_id'){row.gateway.required=true;return;}if(field==='cash_check_id'){row.check.required=true;return;}
                const label=document.createElement('label');label.className=options.compact?'payment-detail-field':'col-md-4';label.textContent=field.replaceAll('_',' ');
                const input=document.createElement('input');input.className='form-control';input.dataset.field=field;input.maxLength=500;input.required=true;input.value=values[field]||'';label.append(input);row.fields.append(label);
            });
            if (options.compact) {
                const controls=[row.reference,row.date,row.gateway,row.check,...row.fields.querySelectorAll('input')];
                row.requiredControls=controls.filter(control=>control.required);
                controls.forEach(control=>{control.required=false;});
                for (const key of ['gateway','check']) if (row[key+'Holder']) row[key+'Holder'].hidden=!row.requiredControls.includes(row[key]) && !row[key].value;
                row.reference.closest('label').hidden=!row.requiredControls.includes(row.reference) && !row.reference.value;
                row.date.closest('label').hidden=!row.requiredControls.includes(row.date) && !row.date.value;
                row.detailsButton.hidden=!row.requiredControls.length && !row.reference.value && !row.date.value && !row.gateway.value && !row.check.value && !Object.values(values).some(Boolean);
            }
        };
        rows.push(row);container.append(element);fields();
        row.select.addEventListener('change',()=>{row.reference.required=false;row.date.required=false;fields();update();});
        row.base.addEventListener('change',()=>{fields();update();});
        element.addEventListener('input',update);row.gateway.addEventListener('change',update);row.check.addEventListener('change',update);
        row.remove.addEventListener('click',()=>{rows.splice(rows.indexOf(row),1);element.remove();update();});update();
    };
    return {data,add,canAdd,
        load(payments){rows.splice(0);container.replaceChildren();payments.forEach(payment=>add(payment));update();},
        restoreInput(input, originals = []) {
            rows.splice(0);container.replaceChildren();
            Object.values(input).forEach(line=>{
                const original=originals.find(payment=>payment.id===line.saved_payment_id);
                const method=methods.find(method=>method.id===line.payment_method_id);
                const same=original && String(original.payment_method_id||'')===String(line.payment_method_id||'') && cents(Number(original.base_amount)>0?original.base_amount:Number(original.amount)-Number(original.surcharge_amount||0))===cents(line.base_amount);
                const rate=same?Number(original.surcharge_rate||0):(options.collection?0:Number(method?.percentage||0));
                const base=cents(line.base_amount); const fee=Math.round(base*Math.round(rate*100)/10000);
                add({...original,id:line.saved_payment_id||'',payment_method_id:line.payment_method_id||'',payment_method:method?.type==='wallet'?'qr':method?.type||original?.payment_method,
                    base_amount:base/100,surcharge_rate:rate,surcharge_amount:fee/100,amount:(base+fee)/100,
                    payment_data:JSON.stringify(line.fields||{}),reference:line.reference||'',external_reference:line.external_reference||'',notes:line.notes||'',paid_at:line.paid_at||'',gateway_id:line.gateway_id||'',cash_check_id:line.cash_check_id||''});
            });update();
        },
        syncBase(){if(!rows.length){if(!options.allowEmpty)add();return;}if(rows.length===1 && !options.partial && !rows[0].original)rows[0].base.value=getBase().toFixed(2);update();},
        refresh:update,
        reset(){rows.splice(0);container.replaceChildren();if(!options.allowEmpty)add();else update();},
        validate(){if(options.compact){const incomplete=rows.find(row=>row.requiredControls.some(control=>!String(control.value||'').trim() || !control.checkValidity()));if(incomplete){incomplete.openDetails();throw Error('Completa los datos obligatorios del medio de pago.');}}const values=data();if(!values.length && !options.allowEmpty)throw Error('Completa los medios de pago.');if(values.some(line=>(!line.id&&!line.saved_payment_id)||line.base<=0||!line.allowed))throw Error('Revisa los medios de pago y sus importes.');const assigned=values.reduce((sum,line)=>sum+cents(line.base),0);if(!options.collection && (assigned>cents(getBase())||(!options.partial&&assigned!==cents(getBase()))))throw Error('Los importes asignados no coinciden con el total antes de recargos.');},
        appendTo(formData,prefix='kiosk_payments'){data().forEach((line,i)=>{for(const key of ['saved_payment_id','reference','external_reference','gateway_id','cash_check_id','paid_at','notes'])formData.set(prefix+'['+i+']['+key+']',line[key]||'');for(const [key,value]of Object.entries({payment_method_id:line.id,base_amount:line.base.toFixed(2),received_amount:line.received.toFixed(2)}))formData.set(prefix+'['+i+']['+key+']',value);for(const[key,value]of Object.entries(line.fields))formData.set(prefix+'['+i+'][fields]['+key+']',value);});}
    };
};
// Display a tax-inclusive total without changing the base amounts submitted by the form.
window.distributePaymentTotal = (items, targetTotal) => {
    const cents = value => Math.round((Number(value) + Number.EPSILON) * 100);
    const weights = items.map(item => cents(item.gross));
    const weight = weights.reduce((sum, value) => sum + value, 0);
    let accumulated = 0, allocated = 0;
    return items.map((item, index) => {
        accumulated += weights[index];
        const target = weight > 0 ? Math.round(cents(targetTotal) * accumulated / weight) : 0;
        const gross = (target - allocated) / 100;
        allocated = target;
        const net = cents(gross / (1 + Number(item.rate || 0) / 100)) / 100;
        return {...item, gross, net, tax: cents(gross - net) / 100};
    });
};
