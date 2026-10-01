window.createKioskPayments = function (container, methods, getBase, onChange) {
    const money = value => (Number(value) || 0).toLocaleString('es-AR', {minimumFractionDigits:2,maximumFractionDigits:2});
    const cents = value => Math.round((Number(value) || 0) * 100);
    const rows = [];
    const data = () => rows.map(row => {
        const method = methods.find(item => item.id === row.select.value);
        const base = method ? cents(row.base.value) : 0;
        const rate = Math.round(Number(method?.percentage || 0) * 100);
        const surcharge = Math.round(base * rate / 10000);
        const total = base + surcharge;
        const received = total;
        return {id:method?.id || '', code:method?.code || '', show_on_receipt:Number(method?.show_on_receipt ?? 1), type:method?.type === 'wallet' ? 'qr' : method?.type,
            rate:rate/100, base:base/100, surcharge:surcharge/100, total:total/100,
            received:received/100, change:Math.max(0,received-total)/100};
    });
    const canAdd = () => {
        const values = data();
        return rows.length < 20 && !values.some(line=>!line.id)
            && cents(getBase()) > values.reduce((sum,line)=>sum+cents(line.base),0);
    };
    const update = () => {
        const values = data();
        rows.forEach((row,i) => {
            const value = values[i];
            row.fee.textContent = value.rate ? `Recargo ${money(value.rate)} %: ${money(value.surcharge)}` : '';
            row.total.textContent = money(value.total);
            row.remove.disabled = rows.length === 1;
        });
        onChange();
    };
    const add = () => {
        if (rows.length >= 20) return;
        const remaining = Math.max(0,cents(getBase())-data().reduce((sum,line)=>sum+cents(line.base),0))/100;
        if (rows.length && (remaining <= 0 || data().some(line=>!line.id))) return;
        const element = document.createElement('div');
        element.className = 'py-2';
        element.innerHTML = `<div class="d-flex flex-nowrap gap-3 align-items-end" style="min-width:760px">
            <label class="flex-grow-1" style="flex-basis:25%;min-width:170px">Medio de pago<select class="form-select" required></select></label>
            <label style="width:170px;flex-shrink:0">Importe a cubrir<input class="form-control base" type="number" min="0.01" step="0.01" required></label>
            <div style="width:190px;flex-shrink:0"><div class="small text-secondary">A cobrar con este medio</div><strong class="total"></strong><div class="fee small text-secondary"></div></div>
            <div class="d-flex justify-content-end ms-auto"><button type="button" class="btn btn-outline-danger icon-btn remove" title="Eliminar pago" aria-label="Eliminar pago"><i class="bi bi-trash" aria-hidden="true"></i></button></div>
            </div>`;
        const row = {element,select:element.querySelector('select')};
        for (const key of ['base','fee','total','remove']) row[key]=element.querySelector('.'+key);
        row.select.add(new Option('Seleccionar medio de pago',''));
        methods.forEach(method => { const option=new Option(method.code,method.id);option.disabled=!['cash','card','transfer','check','wallet'].includes(method.type);row.select.add(option); });
        row.base.value = remaining.toFixed(2);
        rows.push(row);container.append(element);
        row.select.addEventListener('change',update);
        row.base.addEventListener('input',update);
        row.remove.addEventListener('click',()=>{rows.splice(rows.indexOf(row),1);element.remove();update();});
        update();
    };
    return {
        data, add, canAdd,
        syncBase() {
            if (!rows.length) { add();return; }
            if (rows.length === 1) {
                rows[0].base.value=getBase().toFixed(2);
            }
            update();
        },
        reset() { rows.splice(0);container.replaceChildren();add(); },
        validate() {
            const values=data();
            if (!values.length || values.some(line=>!line.id || line.base<=0)) throw Error('Completa los medios de pago y sus importes.');
            if (values.reduce((sum,line)=>sum+cents(line.base),0)!==cents(getBase())) throw Error('Los importes asignados deben cubrir exactamente el total antes de recargos.');
        },
        appendTo(formData) {
            data().forEach((line,i)=>{
                for (const [key,value] of Object.entries({payment_method_id:line.id,base_amount:line.base.toFixed(2),received_amount:line.received.toFixed(2)})) {
                    formData.set(`kiosk_payments[${i}][${key}]`,value);
                }
            });
        }
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
