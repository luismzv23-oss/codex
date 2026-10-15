(() => {
    'use strict';
    const form = document.querySelector('[data-delivery-invoice]');
    if (!form) return;
    const documentSelect = form.querySelector('[data-delivery-document]');
    const pointSelect = form.querySelector('[data-delivery-point]');
    const pointHint = form.querySelector('[data-delivery-point-hint]');
    const actionButtons = [...form.querySelectorAll('button[name="operation"]')];
    const originalDisabled = new Map(actionButtons.map(button => [button, button.disabled]));
    function filterPoints() {
        if (!documentSelect || !pointSelect) return;
        const documentOption = documentSelect.selectedOptions[0];
        const documentId = documentSelect.value;
        const channel = documentOption?.dataset.channel || 'standard';
        const valid = [];
        [...pointSelect.options].forEach(option => {
            if (!option.value) return;
            const pointChannel = option.dataset.channel || 'standard';
            const supported = ['standard', 'kiosk', 'both'].includes(channel) && ['standard', 'kiosk', 'both'].includes(pointChannel);
            const matches = Boolean(documentId && supported && (!option.dataset.document || option.dataset.document === documentId) && (pointChannel === 'both' || channel === 'both' || pointChannel === channel));
            option.hidden = !matches; option.disabled = !matches;
            if (matches) valid.push(option);
        });
        if (!valid.some(option => option.value === pointSelect.value)) pointSelect.value = valid.length === 1 ? valid[0].value : '';
        pointSelect.disabled = valid.length === 0;
        if (pointHint) pointHint.textContent = !documentId ? 'Primero selecciona el tipo de factura.' : (!valid.length ? 'No hay un punto de venta activo compatible. Revisa la configuración de puntos de venta.' : '');
        actionButtons.forEach(button => {button.disabled = originalDisabled.get(button) || !pointSelect.value;});
    }
    documentSelect?.addEventListener('change', filterPoints);
    pointSelect?.addEventListener('change', filterPoints);
    filterPoints();
    const format = cents => (cents / 100).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function recalculate() {
        let net = 0, tax = 0;
        form.querySelectorAll('[data-delivery-line]').forEach(row => {
            const qty = Math.round((Number(row.querySelector('[data-delivery-quantity]').value) || 0) * 10000);
            const original = Number(row.dataset.original), used = Number(row.dataset.used), cents = Number(row.dataset.cents);
            const gross = Math.round(cents * (used + qty) / original) - Math.round(cents * used / original);
            const lineNet = Math.round(gross / (1 + Number(row.dataset.tax) / 100));
            row.querySelector('[data-delivery-amount]').textContent = format(gross);
            net += lineNet; tax += gross - lineNet;
        });
        form.querySelector('[data-delivery-net]').textContent = format(net);
        form.querySelector('[data-delivery-tax]').textContent = format(tax);
        form.querySelector('[data-delivery-total]').textContent = format(net + tax);
    }
    form.addEventListener('input', recalculate);
    form.addEventListener('submit', event => {
        if (event.submitter?.formMethod === 'get') return;
        // Preserve the clicked operation in the native POST while preventing a second submission.
        if (form.dataset.submitting) {event.preventDefault(); return;}
        form.dataset.submitting = '1';
        setTimeout(() => form.querySelectorAll('button').forEach(button => {button.disabled = true;}), 0);
    });
    recalculate();
})();
