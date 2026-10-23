const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const handlers = {};
let requests = 0;
let modal = false;
const document = {
    hidden: false,
    querySelector: () => modal ? {} : null,
    addEventListener: (type, handler) => { handlers[type] = handler; },
};
const window = {
    reloadCashData: () => { requests++; },
    addEventListener: (type, handler) => { handlers[type] = handler; },
};
vm.runInNewContext(readFileSync('public/assets/js/cash-dashboard.js', 'utf8'), {document, window, Alpine: {data: (_name, factory) => factory().init()}});
handlers['alpine:init']();
handlers.click({target: {closest: () => ({})}, preventDefault() {}});
assert.equal(requests, 1, 'Manual refresh uses the full-table refresh exactly once');
handlers.focus();
assert.equal(requests, 2, 'Returning from seller window refreshes register states');
document.hidden = true;
handlers.visibilitychange();
assert.equal(requests, 2, 'Hidden page does not refresh');
document.hidden = false;
handlers.visibilitychange();
assert.equal(requests, 3, 'Returning to the tab refreshes');
modal = true;
handlers.focus();
assert.equal(requests, 3, 'An active cash form is not interrupted');
console.log('Cash refresh: 5 checks passed');
