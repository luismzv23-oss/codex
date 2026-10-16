const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');const fs=require('node:fs');const path=require('node:path');
const script=fs.readFileSync(path.join(__dirname,'../../public/assets/js/kiosk-payments.js'),'utf8');
class Element {
    constructor(tag='div'){
        this.tag=tag;this.tagName=tag.toUpperCase();this.children=[];this.listeners={};this.dataset={};this.value='';this.options=[];this.disabled=false;this.textContent='';this.required=false;this.hidden=false;
        const classes=new Set();this.classList={add:(...values)=>values.forEach(value=>classes.add(value)),contains:value=>classes.has(value)};
    }
    set innerHTML(value){
        this.html=value;this.parts={};
        for(const key of ['base','fee','total','remove','status','reference','date','notes','fields'])this.parts['.'+key]=new Element(key==='fields'?'div':'input');
        this.parts.select=new Element('select');
        for(const key of ['reference','date']) {const label=new Element('label');label.append(this.parts['.'+key]);}
        for(const key of ['payment-details','details-toggle','details-close','details-accept'])this.parts['.'+key]=new Element(key==='payment-details'?'dialog':'button');
    }
    querySelector(key){return this.parts?.[key]||null;}
    querySelectorAll(key){return this.children.flatMap(child=>[...(key==='input'&&child.tag==='input'?[child]:[]),...child.querySelectorAll(key)]);}
    closest(tag){return this.parent?.tag===tag?this.parent:this.parent?.closest(tag);}
    append(...items){items.forEach(item=>{item.parent=this;this.children.push(item);});}
    before(item){this.beforeItems??=[];this.beforeItems.push(item);} remove(){this.removed=true;}
    replaceChildren(...items){this.children=[];this.append(...items);}
    add(option){this.options.push(option);}
    addEventListener(name,callback){this.listeners[name]=callback;}
    showModal(){this.open=true;}
    close(){this.open=false;this.listeners.close?.();}
    focus(){this.focused=true;}
    checkValidity(){return !this.required || Boolean(this.value.trim());}
    reportValidity(){this.reported=true;return this.checkValidity();}
}

const methods=[{id:'card',code:'NARANJA',name:'Tarjeta Naranja',type:'card',percentage:7,currency_ids:['ars'],currency_codes:['ARS'],branch_ids:['main'],point_of_sale_ids:['pos'],required_fields:[],requires_confirmation:0}];
function component(options={},catalog=methods){const window={};vm.runInNewContext(script,{window,document:{createElement:tag=>new Element(tag)},Option:function(text,value){this.textContent=text;this.value=value;},console});const container=new Element();const api=window.createKioskPayments(container,catalog,()=>100,()=>{}, {partial:true,allowEmpty:true,context:()=>({currency_code:'ARS',branch_id:'main',point_of_sale_id:'pos'}),...options});return {api,container};}
function selectRow(ui,id,amount){ui.api.add();const row=ui.container.children.at(-1);row.querySelector('select').value=id;row.querySelector('.base').value=String(amount);row.querySelector('select').listeners.change();return row;}

test('Draft can be saved without payments or with a partial configured card',()=>{const ui=component();ui.api.validate();selectRow(ui,'card',40);ui.api.validate();assert.equal(ui.api.data()[0].total,42.8);});
test('Historical payment is reopened without choosing a configured card by its type',()=>{const ui=component();ui.api.load([{id:'old',payment_method:'card',amount:50,status:'registered'}]);ui.api.validate();assert.equal(ui.api.data()[0].id,'');assert.equal(ui.api.data()[0].saved_payment_id,'old');assert.equal(ui.api.data()[0].total,50);});
test('Reopening a saved configured card does not apply the new catalog percentage',()=>{const ui=component();ui.api.load([{id:'saved',payment_method_id:'card',payment_method:'card',amount:105,base_amount:100,surcharge_rate:5,surcharge_amount:5,status:'pending'}]);const row=ui.api.data()[0];assert.equal(row.total,105);assert.equal(row.rate,5);assert.equal(row.pending,true);});
test('A new payment outside its currency scope cannot be submitted',()=>{const ui=component({context:()=>({currency_code:'USD',branch_id:'main',point_of_sale_id:'pos'})});const row=selectRow(ui,'card',40);assert.equal(row.querySelector('select').options.find(o=>o.value==='card').disabled,true);assert.throws(()=>ui.api.validate(),/Revisa/);});
test('Configured confirmation flags apply to cards as well as transfers',()=>{const ui=component({},[{...methods[0],requires_confirmation:1}]);selectRow(ui,'card',40);assert.equal(ui.api.data()[0].pending,true);});
test('Collection does not add a second surcharge and sends configured IDs',()=>{const ui=component({collection:true,allowEmpty:false});selectRow(ui,'card',100);const values=new Map();ui.api.appendTo({set:(k,v)=>values.set(k,v)},'payments');assert.equal(ui.api.data()[0].total,100);assert.equal(values.get('payments[0][payment_method_id]'),'card');assert.equal(values.get('payments[0][base_amount]'),'100.00');});
test('Validation errors restore a new pending card without inventing a historical row',()=>{const ui=component({},[{...methods[0],requires_confirmation:1}]);ui.api.restoreInput([{payment_method_id:'card',base_amount:'40.00',reference:'REF'}]);assert.equal(ui.api.data()[0].pending,true);assert.equal(ui.api.data()[0].saved_payment_id,'');assert.equal(ui.api.data()[0].reference,'REF');});

test('Methods disabled for collections are unavailable in sales and receipts',()=>{const ui=component({},[{...methods[0],allow_collections:0}]);const row=selectRow(ui,'card',40);assert.equal(row.querySelector('select').options.find(o=>o.value==='card').disabled,true);assert.throws(()=>ui.api.validate(),/Revisa/);});

test('Compact payments use one grid row with no reference, date or note in the main line',()=>{
    const ui=component({compact:true});const row=selectRow(ui,'card',40);
    assert.equal(ui.container.classList.contains('company-payments--compact'),true);
    const main=row.html.split('<dialog')[0];
    assert.match(main,/payment-line-main/);assert.doesNotMatch(main,/Referencia|Fecha|Nota|row g-2/);
    assert.equal(row.querySelector('.details-toggle').hidden,true);
});
test('Missing compact required reference opens its dialog before submission',()=>{
    const ui=component({compact:true},[{...methods[0],required_fields:['referencia']}]);const row=selectRow(ui,'card',40);
    assert.equal(row.querySelector('.details-toggle').hidden,false);
    assert.equal(row.querySelector('.reference').required,false,'Closed dialogs must not trap native form validation');
    assert.throws(()=>ui.api.validate(),/datos obligatorios/);
    assert.equal(row.querySelector('.payment-details').open,true);assert.equal(row.querySelector('.reference').focused,true);
    row.querySelector('.details-accept').listeners.click();assert.equal(row.querySelector('.payment-details').open,true);
    row.querySelector('.reference').value='REF-123';row.querySelector('.details-accept').listeners.click();
    assert.equal(row.querySelector('.payment-details').open,false);assert.equal(row.querySelector('.reference').required,false);
    ui.api.validate();assert.equal(ui.api.data()[0].reference,'REF-123');
});
test('Compact rows retain historical notes, reference and date in their submitted payload',()=>{
    const ui=component({compact:true});ui.api.load([{id:'saved',payment_method_id:'card',payment_method:'card',base_amount:100,amount:105,surcharge_rate:5,surcharge_amount:5,
        reference:'REF-OLD',notes:'Nota guardada',paid_at:'2026-10-16 10:30:00',status:'registered'}]);
    const values=new Map();ui.api.appendTo({set:(key,value)=>values.set(key,value)});
    assert.equal(values.get('kiosk_payments[0][notes]'),'Nota guardada');assert.equal(values.get('kiosk_payments[0][reference]'),'REF-OLD');
    assert.equal(values.get('kiosk_payments[0][paid_at]'),'2026-10-16T10:30');assert.equal(ui.api.data()[0].total,105);
});
test('Compact custom required data survives rebuilding fields and closing the popup',()=>{
    const ui=component({compact:true},[{...methods[0],required_fields:['entidad']}]);const row=selectRow(ui,'card',40);
    assert.throws(()=>ui.api.validate(),/datos obligatorios/);
    row.querySelector('.fields').querySelectorAll('input')[0].value='Entidad guardada';
    row.querySelector('.details-accept').listeners.click();
    row.querySelector('.base').listeners.change();ui.api.validate();
    assert.equal(ui.api.data()[0].fields.entidad,'Entidad guardada');
});
test('Historical snapshot requirements are retained when the catalog changes',()=>{
    const ui=component({compact:true},[{...methods[0],required_fields:['referencia']}]);
    ui.api.load([{id:'saved',payment_method_id:'card',payment_method:'card',base_amount:100,amount:105,surcharge_rate:5,surcharge_amount:5,status:'registered',payment_method_snapshot:JSON.stringify({...methods[0],required_fields:[]})}]);
    ui.api.validate();
    assert.equal(ui.container.children[0].querySelector('.details-toggle').hidden,true);
    ui.container.children[0].querySelector('.base').value='50';
    ui.container.children[0].querySelector('.base').listeners.change();
    assert.throws(()=>ui.api.validate(),/datos obligatorios/);
});

test('Compact validation preserves saved custom metadata outside displayed required fields',()=>{
    const ui=component({compact:true});ui.api.load([{id:'saved',payment_method_id:'card',payment_method:'card',base_amount:100,amount:105,surcharge_rate:5,surcharge_amount:5,status:'registered',payment_data:JSON.stringify({autorizacion:'ABC-123'})}]);
    assert.equal(ui.api.data()[0].fields.autorizacion,'ABC-123');
});
test('Configured gateway requirement is completed in compact payment details',()=>{
    const ui=component({compact:true,gateways:[{id:'gateway',name:'Entidad de cobro'}]},[{...methods[0],required_fields:['gateway_id']}]);
    const row=selectRow(ui,'card',40);assert.throws(()=>ui.api.validate(),/datos obligatorios/);
    assert.equal(row.querySelector('.payment-details').open,true);
    const holder=row.querySelector('.fields').beforeItems.find(item=>item.textContent==='Pasarela');
    assert.equal(holder.hidden,false);
    holder.children[0].value='gateway';
    row.querySelector('.details-accept').listeners.click();ui.api.validate();
    assert.equal(ui.api.data()[0].gateway_id,'gateway');
    assert.equal(row.querySelector('.payment-details').open,false);
});
