(() => {
    document.querySelectorAll('[data-payment-lines]').forEach(root => {
        let next = 0;
        const rows = root.querySelector('[data-payment-rows]');
        const add = () => {
            const fragment = root.querySelector('template').content.cloneNode(true);
            fragment.querySelectorAll('[data-field]').forEach(input => { input.name = `payments[${next}][${input.dataset.field}]`; });
            const row = fragment.querySelector('[data-payment-row]');
            const method = row.querySelector('[data-field="payment_method"]');
            method.addEventListener('change', () => { row.querySelector('[data-field="external_reference"]').required = method.value === 'transfer'; });
            row.querySelector('[data-remove-payment]').addEventListener('click', () => { if (rows.children.length > 1) row.remove(); });
            rows.append(fragment); next++;
        };
        root.querySelector('[data-add-payment]').addEventListener('click', add);
        add();
    });
})();
