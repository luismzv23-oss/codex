(() => {
    'use strict';
    const configElement = document.getElementById('treasury-config');
    if (!configElement) return;
    const config = JSON.parse(configElement.textContent), form = configElement.closest('form');
    const method = form.querySelector('[name="payment_method_id"]'), currency = form.querySelector('[name="currency_code"]');
    const point = form.querySelector('[name="point_of_sale_id"]'), session = form.querySelector('[name="cash_session_id"]');
    const movement = form.querySelector('[name="movement_type"]'), direction = form.querySelector('[name="adjustment_direction"]');
    const fields = document.getElementById('treasury-required'), notice = document.getElementById('treasury-policy');
    const values = {...(config.old.fields || {})};
    function list(value) { return Array.isArray(value) ? value : JSON.parse(value || '[]'); }
    function operation() {
        if (!movement) return config.operation;
        return ['manual_expense','withdrawal'].includes(movement.value) || (movement.value === 'adjustment' && direction?.value === 'expense') ? 'supplier' : 'collection';
    }
    function refresh() {
        fields.querySelectorAll('input').forEach(input => { values[input.dataset.field] = input.value; });
        const branch = session ? (config.sessionBranches[session.value] || '') : (config.branchId || '');
        const pointBranch = point.selectedOptions[0]?.dataset.branch || '';
        const currencyId = currency.selectedOptions[0]?.dataset.id;
        for (const option of method.options) {
            const row = config.catalog.find(m => m.id === option.value);
            if (!row) continue;
            const permitted = operation() === 'supplier' ? Number(row.allow_supplier_payments || 0) === 1 : Number(row.allow_collections ?? 1) === 1;
            option.disabled = !permitted || !['cash','card','transfer','check','wallet'].includes(row.type)
                || (list(row.currency_ids).length && !list(row.currency_ids).includes(currencyId))
                || (list(row.branch_ids).length && !list(row.branch_ids).includes(branch || pointBranch))
                || (list(row.point_of_sale_ids).length && !list(row.point_of_sale_ids).includes(point.value))
                || Boolean(branch && pointBranch && branch !== pointBranch);
        }
        const row = config.catalog.find(m => m.id === method.value);
        method.setCustomValidity(method.selectedOptions[0]?.disabled ? 'El medio no está habilitado para esta operación y ubicación.' : '');
        fields.replaceChildren();
        if (row) {
            for (const field of list(row.required_fields)) {
                const wrapper = document.createElement('div'); wrapper.className = 'col-md-6';
                const label = document.createElement('label'); label.className = 'form-label'; label.textContent = field.replaceAll('_',' ');
                const input = document.createElement('input'); input.className = 'form-control'; input.name = 'fields[' + field + ']';
                input.dataset.field = field; input.id = 'treasury-field-' + field; label.htmlFor = input.id;
                input.value = values[field] ?? ''; input.maxLength = 500; input.required = true;
                wrapper.append(label,input); fields.append(wrapper);
            }
        }
        notice.textContent = row ? ((row.type === 'transfer' || Number(row.requires_confirmation)) ? 'Requiere confirmación: el importe no afectará saldos hasta verificarlo. Sin recargos de venta.' : 'El importe se registrará sin recargos de venta.') : 'Selecciona un medio habilitado para esta operación.';
    }
    [method,currency,point,session,movement,direction].filter(Boolean).forEach(input => input.addEventListener('change',refresh));
    refresh();
})();
