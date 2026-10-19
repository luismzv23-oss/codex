const {test} = require('node:test');
const assert = require('node:assert/strict');
const {shortages} = require('../../public/assets/js/sales-pack-stock.js');
const product = (id, units) => ({id, stock_requirements: {base:units}, component_stocks:{base:{w:{available:13}, other:{available:0}}}});
test('mixed presentations share the same thirteen bottles', () => {
    const lines = [{product:product('case',8),quantity:1}, {product:product('half',4),quantity:1}, {product:product('base',1),quantity:1}];
    assert.deepEqual(shortages(lines,'w'), []);
    lines[1].quantity = 2;
    assert.equal(shortages(lines,'w')[0].required,17);
    assert.equal(shortages(lines,'w')[0].available,13);
    assert.equal(shortages(lines,'other')[0].available,0);
});
test('a missing component or warehouse is not available stock', () => {
    assert.equal(shortages([{product:product('case',8),quantity:1}],'missing')[0].available,0);
    assert.deepEqual(shortages([], 'w'), []);
});
