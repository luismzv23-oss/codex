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
    const operationBranch = () => session ? (config.sessionBranches[session.value] || '') : (config.branchId || '');
    /** Points of sale that satisfy a method's point/branch restrictions for the operation branch. */
    function candidatePoints(row) {
        const points = list(row.point_of_sale_ids), branches = list(row.branch_ids), branch = operationBranch();
        return Array.from(point.options).filter(option => option.value
            && (!points.length || points.includes(option.value))
            && (!branches.length || branches.includes(option.dataset.branch || ''))
            && (!branch || (option.dataset.branch || '') === branch));
    }
    /** A location restriction that can still be satisfied by choosing a point of sale. */
    function needsPoint(row, branch, pointBranch) {
        return (list(row.point_of_sale_ids).length && !point.value) || (list(row.branch_ids).length && !branch && !pointBranch);
    }
    let assignedPoint = '';
    /** A selected method limited to points of sale gets the first enabled point while none is chosen. */
    function assignPoint() {
        const row = config.catalog.find(m => m.id === method.value);
        if (!row || !needsPoint(row, operationBranch(), point.selectedOptions[0]?.dataset.branch || '')) return;
        const first = candidatePoints(row)[0];
        if (first) { point.value = first.value; assignedPoint = first.value; }
    }
    function refresh() {
        fields.querySelectorAll('input').forEach(input => { values[input.dataset.field] = input.value; });
        // Also covers methods preselected on load (browser form restore or returned old input).
        assignPoint();
        const branch = operationBranch();
        const pointBranch = point.selectedOptions[0]?.dataset.branch || '';
        const currencyId = currency.selectedOptions[0]?.dataset.id;
        for (const option of method.options) {
            const row = config.catalog.find(m => m.id === option.value);
            if (!row) continue;
            const permitted = operation() === 'supplier' ? Number(row.allow_supplier_payments || 0) === 1 : Number(row.allow_collections ?? 1) === 1;
            const location = needsPoint(row, branch, pointBranch)
                // Not chosen yet: available when an enabled point of sale exists (it is assigned on selection).
                ? candidatePoints(row).length === 0
                : (list(row.branch_ids).length && !list(row.branch_ids).includes(branch || pointBranch))
                    || (list(row.point_of_sale_ids).length && !list(row.point_of_sale_ids).includes(point.value));
            option.disabled = !permitted || !['cash','card','transfer','check','wallet'].includes(row.type)
                || (list(row.currency_ids).length && !list(row.currency_ids).includes(currencyId))
                || Boolean(location)
                || Boolean(branch && pointBranch && branch !== pointBranch);
        }
        const row = config.catalog.find(m => m.id === method.value);
        method.setCustomValidity(method.selectedOptions[0]?.disabled ? 'El medio no está habilitado para esta operación y ubicación.'
            : row && needsPoint(row, branch, pointBranch) ? 'Selecciona el punto de venta habilitado para este medio.' : '');
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
        notice.textContent = (row ? ((row.type === 'transfer' || Number(row.requires_confirmation)) ? 'Requiere confirmación: el importe no afectará saldos hasta verificarlo. Sin recargos de venta.' : 'El importe se registrará sin recargos de venta.') : 'Selecciona un medio habilitado para esta operación.')
            + (row && assignedPoint && assignedPoint === point.value ? ' Punto de venta asignado según el medio: ' + (point.selectedOptions[0]?.textContent || assignedPoint).trim() + '.' : '');
    }
    [method,currency,point,session,movement,direction].filter(Boolean).forEach(input => input.addEventListener('change',refresh));
    // Browsers may restore form values after scripts run (reload / back): re-evaluate when the page is shown.
    if (typeof window !== 'undefined') window.addEventListener('pageshow', refresh);
    refresh();
})();
