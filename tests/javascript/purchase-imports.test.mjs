import {test} from 'node:test';
import assert from 'node:assert/strict';
import {totals,mergeSuggestions,highlightRect,normalizeImportItem,importDecimal} from '../../public/assets/js/purchase-import-utils.mjs';
test('Calculated import VAT replaces stale zero and decimal rounding matches the server',()=>{
    const item=normalizeImportItem({quantity:'15',unit_cost:'7149.580000',tax_rate:'21.0000',tax_amount:'0.00'});
    assert.equal(item.unit_cost,'7149.58');assert.equal(item.tax_amount,'');
    assert.equal(totals({items:[item]}).tax,22521.18);
    assert.equal(importDecimal('1.005'),'1.01');assert.equal(importDecimal('1.275'),'1.28');
});
test('Talca fifteen lines reconcile with perceptions, internal taxes and one cent rounding',()=>{
    const pairs=[[15,'7149.58'],[20,'7149.58'],[10,'7149.58'],[15,'7408.32'],[5,'5829.61'],[5,'5829.61'],[5,'5829.61'],[5,'6040.58'],[15,'3744.63'],[15,'3947.93'],[10,'4972.73'],[2,'6748.65'],[2,'6748.65'],[2,'6748.65'],[2,'6992.89']];
    const items=pairs.map(([quantity,unit_cost])=>normalizeImportItem({quantity,unit_cost,tax_rate:'21',tax_amount:'0'}));
    const result=totals({items,adjustments:['23102.89','23102.89','45597.62','0.01'].map(amount=>({kind:'tax',amount})),reviewed_total:'1023619.96'});
    assert.deepEqual(result,{net:770096.33,tax:161720.22,extra:91803.41,total:1023619.96,difference:0});
});
test('Invoice totals reconcile cents including explicit VAT, taxes and discounts',()=>{
    const value=totals({items:[{quantity:'2',unit_cost:'100',tax_rate:'21',tax_amount:'42'}],adjustments:[
        {kind:'charge',amount:'10'},{kind:'discount',amount:'5'},{kind:'vat',amount:'1'},{kind:'tax',amount:'2'}
    ],reviewed_total:'250'});
    assert.deepEqual(value,{net:200,tax:42,extra:8,total:250,difference:0});
});
test('Blank VAT amount is calculated while an explicit zero is preserved',()=>{
    assert.equal(totals({items:[{quantity:1,unit_cost:100,tax_rate:21,tax_amount:''}]}).tax,21);
    assert.equal(totals({items:[{quantity:1,unit_cost:100,tax_rate:21,tax_amount:'0.00'}]}).tax,0);
});
test('Applying extraction fills empty fields without overwriting manual corrections',()=>{
    const current={supplier_id:'manual',notes:'Revisado',identity_reviewed:true,items:[],adjustments:[]};
    const suggestions={supplier_id:'detected',invoice_sequence:'123',items:[{description:'Producto'}],identity_reviewed:true};
    const merged=mergeSuggestions(current,suggestions);
    assert.equal(merged.supplier_id,'manual');assert.equal(merged.notes,'Revisado');assert.equal(merged.invoice_sequence,'123');
    assert.equal(merged.identity_reviewed,false);assert.equal(merged.items.length,1);assert.equal(current.items.length,0);
});
test('Source highlight normalizes coordinates to document proportions',()=>{
    const rectangle=highlightRect({polygon:[1,2,3,2,3,4,1,4]},{width:10,height:10});
    assert.deepEqual(rectangle,{left:10,top:20,width:20,height:20});
});
test('Invalid source positions never produce unsafe CSS coordinates',()=>{
    assert.equal(highlightRect({polygon:[0,0,1,0,1,1,0,Infinity]},{width:1,height:1}),null);
    assert.equal(highlightRect({polygon:[]},{width:0,height:0}),null);
});
test('Invoice comparison does not silently accept an absent or different document total',()=>{
    assert.equal(totals({items:[]}).difference,null);
    assert.equal(totals({items:[{quantity:'1',unit_cost:'100',tax_rate:0}],reviewed_total:'99.99'}).difference,.01);
});
