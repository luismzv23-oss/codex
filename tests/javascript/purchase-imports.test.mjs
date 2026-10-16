import {test} from 'node:test';
import assert from 'node:assert/strict';
import {totals,mergeSuggestions,highlightRect} from '../../public/assets/js/purchase-import-utils.mjs';
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
