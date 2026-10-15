const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../public/assets/js/delivery-invoice.js'), 'utf8');

function formHarness(selectedDocument = 'invoice', selectedPoint = 'kiosk', blocked = false) {
    const documents = {invoice: {dataset: {channel: 'standard'}}, kioskInvoice: {dataset: {channel: 'kiosk'}}, missing: {dataset: {channel: 'standard'}}};
    const documentEvents = {}, pointEvents = {};
    const documentSelect = {value: selectedDocument, get selectedOptions() {return [documents[this.value]];}, addEventListener: (name, callback) => {documentEvents[name] = callback;}};
    const options = [
        {value: '', dataset: {}},
        {value: 'principal', dataset: {channel: 'standard', document: 'invoice'}},
        {value: 'kiosk', dataset: {channel: 'kiosk', document: 'kioskInvoice'}},
    ];
    const point = {value: selectedPoint, options, addEventListener: (name, callback) => {pointEvents[name] = callback;}};
    const hint = {}, buttons = [{disabled: blocked}, {disabled: blocked}];
    const selectors = {'[data-delivery-document]': documentSelect, '[data-delivery-point]': point, '[data-delivery-point-hint]': hint, '[data-delivery-net]': {}, '[data-delivery-tax]': {}, '[data-delivery-total]': {}};
    const form = {dataset: {}, querySelector: name => selectors[name], querySelectorAll: name => name === 'button[name="operation"]' ? buttons : [], addEventListener: () => {}};
    vm.runInNewContext(script, {document: {querySelector: () => form}, setTimeout});
    return {documentSelect, point, hint, buttons, options, changeDocument(id) {documentSelect.value = id; documentEvents.change();}};
}

test('Factura B replaces an incompatible kiosk point with the principal point', () => {
    const ui = formHarness();
    assert.equal(ui.point.value, 'principal');
    assert.equal(ui.options[2].hidden, true);
    assert.equal(ui.options[2].disabled, true);
    assert.equal(ui.buttons[0].disabled, false);
});
test('Changing the invoice refreshes available points and selection', () => {
    const ui = formHarness(); ui.changeDocument('kioskInvoice');
    assert.equal(ui.point.value, 'kiosk');
    assert.equal(ui.options[1].disabled, true);
    assert.equal(ui.options[2].disabled, false);
});
test('No compatible point blocks saving with a recoverable explanation', () => {
    const ui = formHarness(); ui.changeDocument('missing');
    assert.equal(ui.point.value, ''); assert.equal(ui.point.disabled, true);
    assert.equal(ui.buttons[1].disabled, true);
    assert.match(ui.hint.textContent, /No hay un punto de venta activo compatible/);
    ui.changeDocument('invoice');
    assert.equal(ui.point.value, 'principal'); assert.equal(ui.buttons[1].disabled, false);
});
test('Filtering never reenables a button blocked by a business validation', () => {
    const ui = formHarness('invoice', 'kiosk', true);
    assert.equal(ui.point.value, 'principal'); assert.equal(ui.buttons[1].disabled, true);
});
