const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm'), fs=require('node:fs'), path=require('node:path');
const script=fs.readFileSync(path.join(__dirname,'../../public/assets/js/treasury-payments.js'),'utf8');
class Element {
    constructor(tag='div') { this.tag=tag; this.children=[]; this.options=[]; this.dataset={}; this.listeners={}; this.value=''; this.textContent=''; }
    get selectedOptions() { return this.options.filter(o=>o.value===this.value); }
    append(...children) { this.children.push(...children); }
    replaceChildren() { this.children=[]; }
    querySelectorAll() { return this.children.flatMap(child=>child.tag==='input'?[child]:child.querySelectorAll()); }
    addEventListener(event,callback) { this.listeners[event]=callback; }
    setCustomValidity(message) { this.error=message; }
}
function ui(options={}) {
    const base={id:'cash',code:'CASH',name:'Efectivo',type:'cash',allow_collections:1,allow_supplier_payments:1,currency_ids:['ars'],branch_ids:['main'],point_of_sale_ids:[],required_fields:[]};
    const config={operation:'collection',branchId:'main',sessionBranches:{session:'main'},old:{},catalog:[base,{...base,id:'card',type:'card',allow_supplier_payments:0}],...options};
    const controls={};
    for(const name of ['payment_method_id','currency_code','point_of_sale_id','cash_session_id','movement_type','adjustment_direction']) controls[name]=new Element('select');
    controls.payment_method_id.options=[{value:''},...config.catalog.map(row=>({value:row.id}))];controls.payment_method_id.value=options.selected || 'cash';
    controls.currency_code.value='ARS'; controls.currency_code.options=[{value:'ARS',dataset:{id:'ars'}}];
    controls.point_of_sale_id.options=[{value:'',dataset:{}}];
    controls.cash_session_id.value='session'; controls.movement_type.value='manual_income'; controls.adjustment_direction.value='income';
    const form={querySelector:selector=>controls[selector.match(/name="([^"]+)"/)[1]]};
    if (options.supplier) { controls.movement_type=null; controls.cash_session_id=null; }
    const fields=new Element(), policy=new Element();
    const configElement={textContent:JSON.stringify(config),closest:()=>form};
    const document={getElementById:id=>({'treasury-config':configElement,'treasury-required':fields,'treasury-policy':policy}[id]),createElement:tag=>new Element(tag)};
    vm.runInNewContext(script,{document});
    return {controls,fields,policy,change:control=>control.listeners.change()};
}
test('Cash expense offers only methods enabled for outgoing payments',()=>{
    const app=ui();app.controls.movement_type.value='manual_expense';app.change(app.controls.movement_type);
    assert.equal(app.controls.payment_method_id.options.find(o=>o.value==='card').disabled,true);
    assert.equal(app.controls.payment_method_id.options.find(o=>o.value==='cash').disabled,false);
});
test('A method cannot be used outside the actual register branch',()=>{
    const app=ui({sessionBranches:{session:'other'}});app.controls.cash_session_id.value='session';app.change(app.controls.cash_session_id);
    assert.equal(app.controls.payment_method_id.options.find(o=>o.value==='cash').disabled,true);
    assert.match(app.controls.payment_method_id.error,/ubicación/);
});
test('Supplier methods respect explicit enablement and point restrictions',()=>{
    const app=ui({supplier:true,operation:'supplier'});
    assert.equal(app.controls.payment_method_id.options.find(o=>o.value==='card').disabled,true);
    assert.equal(app.controls.payment_method_id.options.find(o=>o.value==='cash').disabled,false);
});
test('Required custom fields are restored and retained across refreshes',()=>{
    const app=ui({old:{fields:{entidad:'Banco uno'}},catalog:[{id:'cash',code:'CASH',type:'cash',allow_collections:1,required_fields:['entidad']}]});
    let input=app.fields.querySelectorAll()[0];assert.equal(input.name,'fields[entidad]');assert.equal(input.required,true);assert.equal(input.value,'Banco uno');
    input.value='Banco dos';app.change(app.controls.currency_code);assert.equal(app.fields.querySelectorAll()[0].value,'Banco dos');
});
test('Pending confirmation is explained and does not display a sales surcharge',()=>{
    const app=ui({catalog:[{id:'cash',type:'transfer',allow_collections:1,percentage:7}]});
    assert.match(app.policy.textContent,/no afectará saldos/);assert.match(app.policy.textContent,/Sin recargos/);
});
