




(() => {
    const configuredPaymentsDraft = true;
    const paymentBranchId = null;
    const useConfiguredPayments = true;
    const configuredMethods = [{"id":"card","company_id":"a","code":"NARANJA","name":"Tarjeta Naranja","type":"card","active":1,"percentage":7,"currency_ids":["a-ars"],"branch_ids":["a-branch"],"point_of_sale_ids":["a-pos"],"required_fields":[],"requires_confirmation":0,"allows_installments":null,"show_on_receipt":null,"funds_destination":null,"deleted_at":null,"currency_codes":["ARS"]}];
    const paymentDiscounts = [];
    let paymentRows = null;
    let paymentBase = 0;
    let products = [];
    const fetchProducts = window.createSalesProductSearch("http:\/\/localhost:8080\/ventas\/productos\/buscar?company_id=a");
    const taxes = [];
    const customers = [];
    const existingItems = [];
    const submittedPayments = null;
    const existingPayments = [{"id":"legacy","payment_method":"card","amount":50,"status":"registered"}];
    const body = document.getElementById('sale-items-body');
    const openSearchButton = document.getElementById('open-sale-search');
    const searchField = document.getElementById('sale-search');
    const resultsContainer = document.getElementById('sale-search-results');
    const resultsCount = document.getElementById('sale-results-count');
    const paymentsBody = document.getElementById('sale-payments-body');
    const warehouseField = document.getElementById('sale-warehouse');
    const globalDiscount = document.getElementById('global-discount');
    const searchModalElement = document.getElementById('saleSearchModal');
    const acceptSearchButton = document.getElementById('accept-sale-search');

    const customerSelect = document.getElementById('sale-customer-id');
    const customerNameField = document.getElementById('sale-customer-name');
    const customerDocField = document.getElementById('sale-customer-doc');
    const clearCustomerBtn = document.getElementById('clear-customer');
    const openCustomerSearchButton = document.getElementById('open-customer-search');
    const customerSearchField = document.getElementById('customer-search');
    const customerResultsContainer = document.getElementById('customer-search-results');
    const customerResultsCount = document.getElementById('customer-results-count');
    const customerSearchModalElement = document.getElementById('customerSearchModal');

    if (!body || !paymentsBody || !warehouseField || !globalDiscount || !searchField || !resultsContainer || !resultsCount || !openSearchButton || !searchModalElement || !acceptSearchButton || !customerSelect || !customerNameField || !customerDocField || !clearCustomerBtn || !openCustomerSearchButton || !customerSearchField || !customerResultsContainer || !customerResultsCount || !customerSearchModalElement) return;

    const resolveSearchModal = () => {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(searchModalElement);
    };

    const resolveCustomerSearchModal = () => {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(customerSearchModalElement);
    };

    const setSelectValue = (name, value) => {
        const select = document.querySelector(`select[name="${name}"]`);
        if (select && value) {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    const productMap = Object.fromEntries(products.map((p) => [p.id, p]));
    const taxMap = Object.fromEntries(taxes.map((t) => [t.id, t]));
    const defaultTaxObj = taxes.find((t) => Number(t.is_default) === 1) || null;
    const defaultTaxId = defaultTaxObj ? defaultTaxObj.id : '';
    const formatMoney = (value) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
    let itemIndex = 0;
    let paymentIndex = 0;
    const selectedProducts = new Map();

    const taxOptions = (selected = '') => {
        const targetSelected = selected !== '' ? selected : defaultTaxId;
        return ['<option value="">Sin impuesto</option>', ...taxes.map((t) => `<option value="${t.id}" ${targetSelected === t.id ? 'selected' : ''}>${t.name} (${Number(t.rate).toFixed(2)}%)</option>`)].join('');
    };

    const paymentOptions = (selected = '') => [['cash', 'Efectivo'], ['card', 'Tarjeta'], ['transfer', 'Transferencia']].map(([value, label]) => `<option value="${value}" ${selected === value ? 'selected' : ''}>${label}</option>`).join('');

    const availableStock = (productId) => {
        const warehouseId = warehouseField.value;
        const product = productMap[productId];
        const stock = product && product.stocks ? product.stocks[warehouseId] : null;
        return Number(stock && stock.available ? stock.available : 0);
    };

    const syncTotals = () => {
        const stockRows = Array.from(body.querySelectorAll('tr'));
        const shortages = SalesPackStock.shortages(stockRows.map(row => ({
            product: productMap[row.querySelector('.sale-product')?.value],
            quantity: Number(row.querySelector('.sale-quantity')?.value || 0),
        })), warehouseField.value);
        stockRows.forEach(row => {
            const id = row.querySelector('.sale-product')?.value;
            const field = row.querySelector('.sale-quantity');
            const insufficient = shortages.some(item => item.products.includes(id));
            field?.classList.toggle('is-invalid', insufficient);
            if (field) field.title = insufficient ? 'Stock insuficiente considerando unidades y packs del carrito.' : '';
        });
        let subtotal = 0; let taxTotal = 0; let paidTotal = 0;
        const displayRows = [];
        body.querySelectorAll('tr').forEach((row) => {
            const qty = Number(row.querySelector('.sale-quantity')?.value || 0);
            const price = Number(row.querySelector('.sale-unit-price')?.value || 0);
            const discount = Number(row.querySelector('.sale-discount-rate')?.value || 0);
            const taxId = row.querySelector('.sale-tax-id')?.value || '';
            const discountAmount = Math.round(qty * price * discount) / 100;
            const lineGross = Math.max(0, Math.round((qty * price - discountAmount) * 100) / 100);
            const taxRate = Number((taxMap[taxId] || {}).rate || 0);
            const net = Math.round((taxRate > 0 ? (lineGross / (1 + (taxRate / 100))) : lineGross) * 100) / 100;
            const tax = Math.round((lineGross - net) * 100) / 100;
            subtotal += net;
            taxTotal += tax;
            displayRows.push({row, gross:lineGross, rate:taxRate});
        });
        paymentsBody.querySelectorAll('.sale-payment-amount').forEach((field) => { paidTotal += Number(field.value || 0); });
        const types = (paymentRows?.data() || []).map(line => line.type);
        const policy = paymentDiscounts.find(row => types.includes(row.payment_method));
        const paymentDiscount = policy ? Number(policy.fixed_discount || 0) + subtotal * Number(policy.discount_rate || 0) / 100 : 0;
        paymentBase = Math.max(0, Math.round((subtotal + taxTotal - Number(globalDiscount.value || 0) - paymentDiscount) * 100) / 100);
        const lines = paymentRows?.data() || [];
        const total = paymentBase + lines.reduce((sum,line) => sum + line.surcharge, 0);
        if (useConfiguredPayments) {
            const display = window.distributePaymentTotal(displayRows, total);
            subtotal = display.reduce((sum,line)=>sum+line.net,0);
            taxTotal = display.reduce((sum,line)=>sum+line.tax,0);
            display.forEach(line => { line.row.querySelector('.sale-line-total').textContent = formatMoney(line.gross); });
        }
        if (useConfiguredPayments) {
            paidTotal = lines.filter(line => !line.pending).reduce((sum,line) => sum + line.total, 0);
            const assigned = lines.reduce((sum,line) => sum + line.total, 0);
            const remaining = Math.round((paymentBase - lines.reduce((sum,line) => sum + line.base, 0)) * 100) / 100;
            document.getElementById('pos-payment-total').textContent = formatMoney(total);
            document.getElementById('pos-payment-assigned').textContent = formatMoney(assigned);
            document.getElementById('pos-payment-balance').textContent = formatMoney(Math.abs(remaining));
            document.getElementById('pos-payment-balance-status').className = 'text-end' + (remaining < 0 ? ' text-danger' : remaining === 0 && paymentBase > 0 ? ' text-success' : '');
            document.getElementById('pos-payment-balance-label').textContent = remaining < 0 ? 'Importe excedido' : remaining === 0 && paymentBase > 0 ? 'Total distribuido' : (configuredPaymentsDraft ? 'Saldo a cuenta corriente' : 'Falta pagar');
            document.getElementById('add-sale-payment').disabled = !paymentRows?.canAdd();
            document.getElementById('pos-payment-pending').hidden = !lines.some(line => line.pending);
        }
        document.getElementById('sale-subtotal').textContent = formatMoney(subtotal);
        document.getElementById('sale-tax-total').textContent = formatMoney(taxTotal);
        document.getElementById('sale-paid-total').textContent = formatMoney(paidTotal);
        document.getElementById('sale-total').textContent = formatMoney(total);
    };

    const bindItemRow = (row) => {
        const sync = () => {
            const productId = row.querySelector('.sale-product').value;
            const qtyField = row.querySelector('.sale-quantity');
            const priceField = row.querySelector('.sale-unit-price');
            const discountField = row.querySelector('.sale-discount-rate');
            const taxField = row.querySelector('.sale-tax-id');
            const stockLabel = row.querySelector('.sale-stock-visible');
            const stockHidden = row.querySelector('.sale-stock-snapshot');
            const totalLabel = row.querySelector('.sale-line-total');
            const product = productMap[productId];
            if (product && (!priceField.dataset.touched || priceField.value === '0.00')) {
                priceField.value = Number(product.sale_price || 0).toFixed(2);
            }
            const available = availableStock(productId);
            stockLabel.textContent = product ? `${formatMoney(available)} ${product.unit || 'unidad'}` : '-';
            stockHidden.value = available.toFixed(2);
            const qty = Number(qtyField.value || 0);
            const price = Number(priceField.value || 0);
            const discount = Number(discountField.value || 0);
            const lineGross = Math.max(0, (qty * price) * (1 - (discount / 100)));
            totalLabel.textContent = formatMoney(lineGross);
            qtyField.classList.toggle('is-invalid', Boolean(productId) && qty > available && warehouseField.value !== '');
            syncTotals();
            paymentRows?.syncBase();
        };
        row.querySelectorAll('select, input').forEach((field) => {
            field.addEventListener('change', sync);
            field.addEventListener('input', sync);
        });
        row.querySelector('.sale-unit-price').addEventListener('input', (event) => { event.target.dataset.touched = '1'; });
        row.querySelector('.remove-sale-item').addEventListener('click', () => { row.remove(); syncTotals(); paymentRows?.syncBase(); });
        sync();
    };

    const addItemRow = (data = {}) => {
        const row = document.createElement('tr');
        const product = productMap[String(data.product_id || '')] || null;
        row.innerHTML = `<td><div class="fw-semibold sale-product-label">${product ? `${product.sku} - ${product.name}` : 'Producto no disponible'}</div><div class="small text-secondary sale-product-brand">${product && product.brand ? product.brand : 'Sin marca'}</div><input type="hidden" name="items[${itemIndex}][product_id]" class="sale-product" value="${String(data.product_id || '')}" required><input type="hidden" name="items[${itemIndex}][available_stock_snapshot]" class="sale-stock-snapshot" value="0"></td><td><input type="number" onkeypress="return event.charCode >= 48 && event.charCode <= 57" name="items[${itemIndex}][quantity]" class="form-control sale-quantity" value="${Number(data.quantity || 1).toFixed(2)}"></td><td><input type="number" step="0.01" min="0" name="items[${itemIndex}][unit_price]" class="form-control sale-unit-price" value="${Number(data.unit_price || (product ? product.sale_price : 0)).toFixed(2)}"></td><td><input type="number" step="0.01" min="0" name="items[${itemIndex}][discount_rate]" class="form-control sale-discount-rate" value="${Number(data.discount_rate || 0).toFixed(2)}"></td><td><select name="items[${itemIndex}][tax_id]" class="form-select sale-tax-id">${taxOptions(String(data.tax_id || ''))}</select></td><td><div class="small text-secondary sale-stock-visible">-</div></td><td><div class="fw-semibold sale-line-total">0,00</div></td><td class="text-end"><button type="button" class="btn btn-outline-dark icon-btn remove-sale-item" title="Quitar" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></td>`;
        body.appendChild(row);
        itemIndex += 1;
        bindItemRow(row);
    };

    const addProductToSale = (product) => {
        const existingRow = Array.from(body.querySelectorAll('tr')).find((row) => row.querySelector('.sale-product')?.value === product.id);
        if (existingRow) {
            const qtyField = existingRow.querySelector('.sale-quantity');
            qtyField.value = Number(qtyField.value || 0) + 1;
            qtyField.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }

        addItemRow({
            product_id: product.id,
            quantity: 1,
            unit_price: Number(product.sale_price || 0),
            discount_rate: 0,
            tax_id: '',
        });
    };

    const matchingProducts = (term) => {
        const normalized = term.trim().toLowerCase();
        if (normalized === '') {
            return [];
        }

        return products.filter((product) => {
            const haystack = `${product.sku} ${product.name} ${product.brand || ''}`.toLowerCase();
            return haystack.includes(normalized);
        }).slice(0, 12);
    };

    const renderResults = (results) => {
        resultsContainer.innerHTML = '';
        resultsCount.textContent = String(results.length);
        if (results.length === 0) { return; }

        results.forEach((product) => {
            const checked = selectedProducts.has(product.id);
            const option = document.createElement('label');
            option.className = 'list-group-item list-group-item-action';
            option.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="d-flex gap-3 align-items-start text-start">
                        <input type="checkbox" class="form-check-input mt-1 sale-search-select" value="${product.id}" ${checked ? 'checked' : ''}>
                        ${product.image
                            ? `<img src="/uploads/products/${product.image}" style="width:40px;height:40px;object-fit:cover;" class="rounded flex-shrink-0">`
                            : `<span class="d-flex align-items-center justify-content-center rounded bg-light text-secondary flex-shrink-0" style="width:40px;height:40px;"><i class="bi bi-box"></i></span>`
                        }
                        <div>
                            <div class="fw-semibold">${product.sku} - ${product.name}</div>
                            <div class="small text-secondary">${product.brand || 'Sin marca'}${product.pack_units ? ` · Pack de ${Number(product.pack_units)} unidades` : ''}</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-semibold">${formatMoney(product.sale_price)}</div>
                        <div class="small text-secondary">Stock ${formatMoney(availableStock(product.id))}</div>
                    </div>
                </div>
            `;
            option.querySelector('.sale-search-select').addEventListener('change', (event) => {
                if (event.target.checked) {
                    selectedProducts.set(product.id, product);
                } else {
                    selectedProducts.delete(product.id);
                }
            });
            resultsContainer.appendChild(option);
        });
    };

    const refreshItemStocks = () => {
        body.querySelectorAll('tr').forEach((row) => {
            const event = new Event('input', { bubbles: true });
            row.querySelector('.sale-quantity')?.dispatchEvent(event);
        });
    };

    const addPaymentRow = (data = {}) => {
        const row = document.createElement('tr');
        row.innerHTML = `<td><select name="payments[${paymentIndex}][payment_method]" class="form-select">${paymentOptions(String(data.payment_method || 'cash'))}</select></td><td><input type="number" step="0.01" min="0" name="payments[${paymentIndex}][amount]" class="form-control sale-payment-amount" value="${Number(data.amount || 0).toFixed(2)}"></td><td><input type="text" name="payments[${paymentIndex}][reference]" class="form-control" value="${String(data.reference || '').replace(/"/g, '&quot;')}"></td><td><input type="datetime-local" name="payments[${paymentIndex}][paid_at]" class="form-control" value="${String(data.paid_at || '')}"></td><td><input type="text" name="payments[${paymentIndex}][notes]" class="form-control" value="${String(data.notes || '').replace(/"/g, '&quot;')}"></td><td class="text-end"><button type="button" class="btn btn-outline-dark icon-btn remove-sale-payment" title="Quitar" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></td>`;
        ['gateway_id', 'cash_check_id', 'external_reference'].forEach(key => { const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = `payments[${paymentIndex}][${key}]`; hidden.value = data[key] || ''; row.cells[0].append(hidden); });
        paymentsBody.appendChild(row);
        row.querySelectorAll('select, input').forEach((field) => { field.addEventListener('change', syncTotals); field.addEventListener('input', syncTotals); });
        row.querySelector('.remove-sale-payment').addEventListener('click', () => { row.remove(); syncTotals(); });
        paymentIndex += 1;
        syncTotals();
    };

    if (useConfiguredPayments) {
        paymentRows = window.createKioskPayments(paymentsBody, configuredMethods, () => paymentBase, () => {
            const previousBase = paymentBase;
            syncTotals();
            if (previousBase !== paymentBase) paymentRows?.syncBase();
        }, {compact:true, gateways:[], checks:[], partial:configuredPaymentsDraft, allowEmpty:configuredPaymentsDraft, context:()=>({
            currency_code:document.querySelector('[name="currency_code"]')?.value || "ARS",
            branch_id:paymentBranchId || document.querySelector('[name="point_of_sale_id"]')?.selectedOptions[0]?.dataset.branch || null, point_of_sale_id:document.querySelector('[name="point_of_sale_id"]')?.value || null
        })});
        const paymentForm = paymentsBody.closest('form');
        paymentForm.querySelectorAll('[name="currency_code"],[name="point_of_sale_id"]').forEach(field=>field.addEventListener('change',()=>paymentRows.refresh()));
        let savingPos = false;
        paymentForm.addEventListener('submit', async event => {
            if (configuredPaymentsDraft) {
                try { paymentRows.validate(); } catch (error) { event.preventDefault(); alert(error.message); }
                return;
            }
            event.preventDefault();
            if (savingPos) return;
            syncTotals();
            try { paymentRows.validate(); } catch (error) { alert(error.message); return; }
            const receiptWindow = window.openSaleReceiptWindow(() => window.location.reload());
            savingPos = true;
            const buttons = Array.from(paymentForm.querySelectorAll('button[type="submit"], button:not([type])'));
            buttons.forEach(button => button.disabled = true);
            let registered = false;
            try {
                const response = await fetch(paymentForm.action, {method:'POST', body:new FormData(paymentForm), headers:{'X-Requested-With':'XMLHttpRequest'}});
                const data = await response.json();
                if (data.csrf_token) {
                    const token = paymentForm.querySelector('input[name="csrf_test_name"]');
                    if (token) token.value = data.csrf_token;
                }
                if (!response.ok || data.status !== 'ok') throw new Error(data.message || 'No se pudo registrar la venta.');
                registered = true;
                receiptWindow.show(data.receipt_url);
            } catch (error) {
                receiptWindow.close();
                alert(registered ? 'La venta se registró. Abre su comprobante desde Ventas; no vuelvas a registrarla.' : error.message);
            } finally {
                if (!registered) { savingPos = false; buttons.forEach(button => button.disabled = false); }
            }
        });
        paymentForm.addEventListener('formdata', event => paymentRows.appendTo(event.formData));
    }
    document.getElementById('add-sale-payment').addEventListener('click', () => useConfiguredPayments ? paymentRows.add() : addPaymentRow());
    warehouseField.addEventListener('change', refreshItemStocks);
    globalDiscount.addEventListener('input', () => { syncTotals(); paymentRows?.syncBase(); });
    const searchProducts = async () => {
        renderResults([]);
        resultsContainer.textContent = 'Actualizando productos…';
        try {
            const result = await fetchProducts(searchField.value);
            if (result === null) return null;
            products = result;
            products.forEach(product => {
                productMap[product.id] = product;
                if (selectedProducts.has(product.id)) selectedProducts.set(product.id, product);
            });
            renderResults(products.slice(0, 12));
            return products;
        } catch (error) {
            products = [];
            renderResults([]);
            resultsContainer.textContent = error.message;
            return null;
        }
    };
    openSearchButton.addEventListener('click', () => {
        const searchModal = resolveSearchModal();
        if (!searchModal) {
            return;
        }
        selectedProducts.clear();
        searchField.value = '';
        renderResults([]);
        resultsCount.textContent = '0';
        searchModal.show();
        searchProducts();
        setTimeout(() => searchField.focus(), 150);
    });
    searchField.addEventListener('input', searchProducts);
    searchField.addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        const results = await searchProducts();
        if (results?.length > 0) {
            const product = results[0];
            if (selectedProducts.has(product.id)) {
                selectedProducts.delete(product.id);
            } else {
                selectedProducts.set(product.id, product);
            }
            renderResults(results);
        }
    });
    acceptSearchButton.addEventListener('click', () => {
        const searchModal = resolveSearchModal();
        selectedProducts.forEach((product) => addProductToSale(product));
        selectedProducts.clear();
        searchField.value = '';
        renderResults([]);
        resultsCount.textContent = '0';
        searchModal?.hide();
    });

    // ── Customer search implementation ───────────────────
    const matchingCustomers = (term) => {
        const normalized = term.trim().toLowerCase();
        if (normalized === '') {
            return [];
        }
        return customers.filter((customer) => {
            const haystack = `${customer.name} ${customer.document_number || ''} ${customer.email || ''} ${customer.phone || ''}`.toLowerCase();
            return haystack.includes(normalized);
        }).slice(0, 12);
    };

    const renderCustomerResults = (results) => {
        customerResultsContainer.innerHTML = '';
        customerResultsCount.textContent = String(results.length);
        if (results.length === 0) { return; }

        results.forEach((customer) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action text-start';
            option.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">${customer.name}</div>
                        <div class="small text-secondary">
                            ${customer.document_type || 'DOC'}: ${customer.document_number || '-'} 
                            ${customer.email ? `· ${customer.email}` : ''}
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark">${customer.tax_profile || 'Cliente'}</span>
                    </div>
                </div>
            `;
            option.addEventListener('click', () => {
                customerSelect.value = customer.id;
                customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
                setSelectValue('price_list_id', customer.price_list_id);
                setSelectValue('sales_agent_id', customer.sales_agent_id);
                setSelectValue('sales_zone_id', customer.sales_zone_id);
                setSelectValue('sales_condition_id', customer.sales_condition_id);
                resolveCustomerSearchModal()?.hide();
            });
            customerResultsContainer.appendChild(option);
        });
    };

    openCustomerSearchButton.addEventListener('click', () => {
        const searchModal = resolveCustomerSearchModal();
        if (!searchModal) return;
        customerSearchField.value = '';
        renderCustomerResults([]);
        customerResultsCount.textContent = '0';
        searchModal.show();
        setTimeout(() => customerSearchField.focus(), 150);
    });

    customerSearchField.addEventListener('input', () => renderCustomerResults(matchingCustomers(customerSearchField.value)));
    customerSearchField.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = customerResultsContainer.querySelector('button');
            if (first) first.click();
        }
    });

    const consumerFinal = customers.find(c => c.name === 'Consumidor Final');
    const consumerFinalId = consumerFinal ? consumerFinal.id : '';

    const updateCustomerInfo = () => {
        const selectedId = customerSelect.value;
        const customer = customers.find(c => c.id === selectedId);
        if (customer) {
            customerNameField.value = customer.name;
            customerDocField.value = `${customer.document_type || 'DOC'} ${customer.document_number || '-'}`;
            if (customer.id !== consumerFinalId) {
                clearCustomerBtn.classList.remove('d-none');
            } else {
                clearCustomerBtn.classList.add('d-none');
            }
        } else {
            customerNameField.value = '';
            customerDocField.value = '';
            clearCustomerBtn.classList.add('d-none');
        }
    };

    customerSelect.addEventListener('change', updateCustomerInfo);

    clearCustomerBtn.addEventListener('click', () => {
        customerSelect.value = consumerFinalId;
        customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
    });

    window.addEventListener('codex:customer-created', (event) => {
        const newCustomer = event.detail.customer;
        if (newCustomer) {
            const idx = customers.findIndex(c => c.id == newCustomer.id);
            if (idx !== -1) {
                customers[idx] = newCustomer;
            } else {
                customers.push(newCustomer);
            }
            customerSelect.value = newCustomer.id;
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
            setSelectValue('price_list_id', newCustomer.price_list_id);
            setSelectValue('sales_agent_id', newCustomer.sales_agent_id);
            setSelectValue('sales_zone_id', newCustomer.sales_zone_id);
            setSelectValue('sales_condition_id', newCustomer.sales_condition_id);
        }
    });

    existingItems.forEach((item) => addItemRow(item));
    if (!useConfiguredPayments) existingPayments.forEach((payment) => addPaymentRow(payment));
    updateCustomerInfo();
    refreshItemStocks();
    syncTotals();
    if (useConfiguredPayments) { if (submittedPayments) paymentRows.restoreInput(submittedPayments, existingPayments); else paymentRows.load(existingPayments); paymentRows.syncBase(); }

    // ARCA checkbox visibility based on document type
    const docTypeSelect = document.querySelector('[name="document_type_id"]');
    const arcaCheckbox  = document.getElementById('pos-authorize-arca');
    const arcaContainer = arcaCheckbox ? arcaCheckbox.closest('.col-12') : null;
    const arcaCategories = ['invoice', 'credit_note', 'debit_note', 'ticket'];

    function updateArcaCheckbox() {
        if (!docTypeSelect || !arcaCheckbox || !arcaContainer) return;
        const selected = docTypeSelect.options[docTypeSelect.selectedIndex];
        const category = selected ? selected.dataset.category : '';
        const allowed  = arcaCategories.includes(category);
        arcaCheckbox.disabled = !allowed;
        arcaContainer.style.display = allowed ? '' : 'none';
        if (!allowed) arcaCheckbox.checked = false;
    }

    docTypeSelect && docTypeSelect.addEventListener('change', updateArcaCheckbox);
    updateArcaCheckbox();
})();





        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.select-search').forEach((el) => {
                new TomSelect(el, { create: false, sortField: { field: "text", direction: "asc" } });
            });
        });
    

            (() => {
                const notifyParent = () => {
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({ type: 'codex-popup-resize' }, window.location.origin);
                    }
                };

                window.addEventListener('load', notifyParent);
                window.addEventListener('resize', notifyParent);
                setTimeout(notifyParent, 80);
            })();
        
