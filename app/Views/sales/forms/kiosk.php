<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$productCatalog = array_values(array_map(static function (array $product): array {
    return [
        'id' => $product['id'],
        'sku' => $product['sku'],
        'name' => $product['name'],
        'brand' => $product['brand'] ?? '',
        'unit' => $product['unit'] ?? 'unidad',
        'price' => (float) ($product['sale_price'] ?? 0),
        'stocks' => $product['stocks'] ?? [],
    ];
}, $products ?? []));
?>
<div id="codex-kiosk-toast" class="codex-kiosk-toast"><i class="bi bi-check-circle-fill"></i><span></span></div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Facturacion kiosco</h1>
        <p class="text-secondary mb-0">Pantalla continua de emision rapida · Escanea codigo de barras o busca por nombre · <kbd>F2</kbd> Cobrar · <kbd>F4</kbd> Cancelar · <kbd>F5</kbd> Imprimir</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (! empty($cashSession)): ?>
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill px-3 py-2">
                <i class="bi bi-unlock-fill me-1"></i>
                <?= esc($cashSession['register_name'] ?? 'Caja Kiosco') ?>
                <span class="text-body-secondary ms-1">· Abierta <?= esc(date('H:i', strtotime($cashSession['opened_at'] ?? 'now'))) ?></span>
            </span>
        <?php else: ?>
            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle rounded-pill px-3 py-2">
                <i class="bi bi-lock-fill me-1"></i> Sin caja activa
            </span>
        <?php endif; ?>
        <a href="<?= site_url('ventas' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark">Volver a Ventas</a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 p-lg-5">
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" id="kiosk-form" class="row g-4">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <input type="hidden" name="pos_mode" value="1">
            <input type="hidden" name="notes" value="Ticket kiosco">

            <div class="col-lg-4">
                <label class="form-label">Deposito</label>
                <select name="warehouse_id" class="form-select" required>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>"><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label">Moneda</label>
                <select name="currency_code" class="form-select" id="kiosk-currency" required>
                    <?php foreach ($currencyOptions as $code => $label): ?>
                        <option value="<?= esc($code) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label">Documento</label>
                <input type="text" class="form-control" value="<?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?>" readonly>
            </div>

            <div class="col-12">
                <div class="row g-4">
                    <div class="col-xl-5">
                        <div class="border rounded-4 p-3 p-lg-4 h-100 bg-light-subtle">
                            <div class="mb-3">
                                <h2 class="h5 mb-1">Buscar productos</h2>
                                <p class="text-secondary mb-0">Escanea el codigo de barras o busca por nombre.</p>
                            </div>

                            <div class="position-relative">
                                <label class="form-label">Producto</label>
                                <input
                                    type="text"
                                    id="kiosk-search"
                                    class="form-control"
                                    placeholder="Escanea o escribe para buscar"
                                    autocomplete="off"
                                >
                                <input type="hidden" id="kiosk-selected-product-id">
                                <div id="kiosk-search-results" class="list-group position-absolute start-0 end-0 mt-2 shadow-sm d-none" style="z-index: 20; max-height: 320px; overflow:auto;"></div>
                            </div>

                            <div class="small text-secondary mt-3">
                                Coincidencias: <span class="fw-semibold" id="kiosk-results-count">0</span>
                                <span class="ms-2" id="kiosk-scan-indicator" style="display:none;">📡 <strong>Barcode detectado</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-7">
                        <div class="border rounded-4 p-3 p-lg-4 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="h5 mb-1">Factura del cliente</h2>
                                    <p class="text-secondary mb-0">Detalle del ticket con cantidad y precio unitario de venta.</p>
                                </div>
                                <div class="text-end">
                                    <div class="small text-secondary">Total</div>
                                    <div class="fs-4 fw-semibold" id="kiosk-total">0,00</div>
                                </div>
                            </div>

                            <div class="border rounded-4 overflow-hidden">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th style="width: 110px;">Cant.</th>
                                                <th style="width: 130px;">Precio</th>
                                                <th style="width: 120px;">Total</th>
                                                <th class="text-end" style="width: 56px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="kiosk-ticket-body">
                                            <tr id="kiosk-empty-row">
                                                <td colspan="5" class="text-secondary py-4">Todavia no agregaste productos a la factura.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-3">
                                    <label class="form-label">Pago</label>
                                    <select name="payments[0][payment_method]" class="form-select" id="kiosk-payment-method">
                                        <option value="cash">Efectivo</option>
                                        <option value="card">Tarjeta</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="mixed">Mixto</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Monto cobrado</label>
                                    <input type="number" step="0.01" min="0" name="payments[0][amount]" class="form-control" id="kiosk-paid-amount" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Vuelto</label>
                                    <div class="codex-kiosk-change" id="kiosk-change">$0,00</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Referencia</label>
                                    <input type="text" name="payments[0][reference]" class="form-control" id="kiosk-reference" value="<?= esc($documentReference ?? '') ?>" readonly>
                                </div>
                            </div>

                            <div id="kiosk-hidden-items"></div>

                            <div class="d-flex justify-content-end gap-2 pt-4">
                                <button type="button" class="btn btn-outline-dark icon-btn" id="kiosk-print-preview" title="Imprimir factura (F5)" aria-label="Imprimir factura"><i class="bi bi-printer"></i></button>
                                <button type="submit" class="btn btn-dark icon-btn" id="kiosk-submit-btn" title="Registrar factura (F2)" aria-label="Registrar factura"><i class="bi bi-check-lg"></i></button>
                                <button type="button" class="btn btn-outline-dark icon-btn" id="kiosk-cancel-button" title="Cancelar factura (F4)" aria-label="Cancelar factura"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const catalog = <?= json_encode($productCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const searchField = document.getElementById('kiosk-search');
    const resultsContainer = document.getElementById('kiosk-search-results');
    const resultsCount = document.getElementById('kiosk-results-count');
    const scanIndicator = document.getElementById('kiosk-scan-indicator');
    const ticketBody = document.getElementById('kiosk-ticket-body');
    const emptyRow = document.getElementById('kiosk-empty-row');
    const totalLabel = document.getElementById('kiosk-total');
    const hiddenItems = document.getElementById('kiosk-hidden-items');
    const paidAmount = document.getElementById('kiosk-paid-amount');
    const changeLabel = document.getElementById('kiosk-change');
    const paymentMethod = document.getElementById('kiosk-payment-method');
    const currencyField = document.getElementById('kiosk-currency');
    const referenceField = document.getElementById('kiosk-reference');
    const form = document.getElementById('kiosk-form');
    const warehouseField = form.querySelector('select[name="warehouse_id"]');
    const printButton = document.getElementById('kiosk-print-preview');
    const cancelButton = document.getElementById('kiosk-cancel-button');
    const submitBtn = document.getElementById('kiosk-submit-btn');
    const toastEl = document.getElementById('codex-kiosk-toast');
    const items = new Map();

    // ── Audio feedback ──────────────────────────────────
    const audioCtx = typeof AudioContext !== 'undefined' ? new AudioContext() : null;
    const playBeep = (freq = 800, duration = 0.1) => {
        if (!audioCtx) return;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = freq;
        gain.gain.value = 0.15;
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
    };
    const beepScan = () => playBeep(1200, 0.08);
    const beepConfirm = () => { playBeep(800, 0.1); setTimeout(() => playBeep(1000, 0.15), 120); };
    const beepError = () => playBeep(300, 0.25);

    // ── Toast ───────────────────────────────────────────
    let toastTimeout = null;
    const showToast = (msg, icon = 'check-circle-fill') => {
        clearTimeout(toastTimeout);
        toastEl.querySelector('i').className = `bi bi-${icon}`;
        toastEl.querySelector('span').textContent = msg;
        toastEl.classList.add('is-visible');
        toastTimeout = setTimeout(() => toastEl.classList.remove('is-visible'), 3500);
    };

    const formatMoney = (value) => new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

    // ── Barcode scanner detection ────────────────────────
    let scanBuffer = '';
    let scanTimer = null;
    const SCAN_THRESHOLD_MS = 80;
    const SCAN_MIN_LENGTH = 4;

    const handlePossibleScan = (code) => {
        const product = catalog.find(p => p.sku === code || p.sku === code.trim());
        if (product) {
            addProduct(product);
            beepScan();
            scanIndicator.style.display = 'inline';
            setTimeout(() => scanIndicator.style.display = 'none', 2000);
        } else {
            beepError();
            showToast('Producto no encontrado: ' + code, 'x-circle-fill');
        }
        searchField.value = '';
        renderResults([]);
    };

    searchField.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            clearTimeout(scanTimer);

            // If buffer looks like a barcode scan (fast input)
            if (scanBuffer.length >= SCAN_MIN_LENGTH) {
                handlePossibleScan(scanBuffer);
                scanBuffer = '';
                return;
            }

            // Otherwise, select first search result
            scanBuffer = '';
            const first = resultsContainer.querySelector('button');
            if (first) first.click();
            return;
        }

        // Accumulate characters for scan detection
        if (event.key.length === 1) {
            clearTimeout(scanTimer);
            scanBuffer += event.key;
            scanTimer = setTimeout(() => { scanBuffer = ''; }, SCAN_THRESHOLD_MS);
        }
    });

    // ── Core functions ──────────────────────────────────
    const syncHiddenInputs = () => {
        hiddenItems.innerHTML = '';
        let index = 0;
        items.forEach((item) => {
            const fields = { product_id: item.product_id || item.id, quantity: item.quantity, unit_price: item.unit_price };
            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${index}][${key}]`;
                input.value = value;
                hiddenItems.appendChild(input);
            });
            const enabled = document.createElement('input');
            enabled.type = 'hidden';
            enabled.name = `items[${index}][enabled]`;
            enabled.value = '1';
            hiddenItems.appendChild(enabled);
            index += 1;
        });
    };

    const totalAmount = () => Array.from(items.values()).reduce((carry, item) => {
        return carry + (Number(item.quantity) * Number(item.unit_price));
    }, 0);

    const availableStockForWarehouse = (product) => {
        const warehouseId = warehouseField?.value || '';
        const stockRow = warehouseId && product.stocks ? product.stocks[warehouseId] : null;
        return Number(stockRow && stockRow.available ? stockRow.available : 0);
    };

    // ── Change (vuelto) calculation ─────────────────────
    const updateChange = () => {
        const total = totalAmount();
        const paid = parseFloat(paidAmount.value) || 0;
        const change = paid - total;
        changeLabel.textContent = '$' + formatMoney(Math.abs(change));
        changeLabel.classList.toggle('is-negative', change < 0);
    };

    paidAmount.addEventListener('input', updateChange);

    const renderTicket = () => {
        ticketBody.innerHTML = '';

        if (items.size === 0) {
            ticketBody.appendChild(emptyRow);
            totalLabel.textContent = '0,00';
            paidAmount.value = '0.00';
            updateChange();
            syncHiddenInputs();
            return;
        }

        items.forEach((item) => {
            const lineTotal = Number(item.quantity) * Number(item.unit_price);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="fw-semibold">${item.sku} - ${item.name}</div>
                    <div class="small text-secondary">${item.brand || 'Sin marca'}</div>
                </td>
                <td>
                    <input
                        type="number"
                        onkeypress="return event.charCode >= 48 && event.charCode <= 57"
                        class="form-control kiosk-qty"
                        data-id="${item.id}"
                        value="${Number(item.quantity).toFixed(0)}"
                    >
                </td>
                <td>
                    <input
                        type="number"
                        class="form-control"
                        value="${Number(item.unit_price).toFixed(2)}"
                        readonly
                    >
                </td>
                <td class="fw-semibold">${formatMoney(lineTotal)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-outline-dark icon-btn kiosk-remove" data-id="${item.id}" title="Quitar" aria-label="Quitar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            `;
            ticketBody.appendChild(row);
        });

        const total = totalAmount();
        totalLabel.textContent = formatMoney(total);
        paidAmount.value = Number(total).toFixed(2);
        updateChange();
        syncHiddenInputs();

        ticketBody.querySelectorAll('.kiosk-qty').forEach((field) => {
            field.addEventListener('input', () => {
                const item = items.get(field.dataset.id);
                if (!item) return;
                item.quantity = Math.max(0.01, Number(field.value || 0));
                renderTicket();
            });
        });

        ticketBody.querySelectorAll('.kiosk-remove').forEach((button) => {
            button.addEventListener('click', () => {
                items.delete(button.dataset.id);
                renderTicket();
            });
        });
    };

    const addProduct = (product) => {
        const existing = items.get(product.id);
        if (existing) {
            existing.product_id = existing.product_id || product.id;
            existing.quantity = Number(existing.quantity) + 1;
        } else {
            items.set(product.id, {
                id: product.id,
                product_id: product.id,
                sku: product.sku,
                name: product.name,
                brand: product.brand || '',
                quantity: 1,
                unit_price: Number(product.price || 0),
            });
        }
        renderTicket();
        searchField.value = '';
        renderResults([]);
        searchField.focus();
    };

    const matchingProducts = (term) => {
        const normalized = term.trim().toLowerCase();
        if (normalized === '') return [];
        return catalog.filter((product) => {
            const haystack = `${product.sku} ${product.name} ${product.brand || ''}`.toLowerCase();
            return haystack.includes(normalized);
        }).slice(0, 12);
    };

    const renderResults = (results) => {
        resultsContainer.innerHTML = '';
        resultsCount.textContent = String(results.length);
        if (results.length === 0) {
            resultsContainer.classList.add('d-none');
            return;
        }
        results.forEach((product) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action';
            option.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div class="text-start">
                        <div class="fw-semibold">${product.sku} - ${product.name}</div>
                        <div class="small text-secondary">${product.brand || 'Sin marca'}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-semibold">${formatMoney(product.price)}</div>
                        <div class="small text-secondary">Stock ${formatMoney(availableStockForWarehouse(product))}</div>
                    </div>
                </div>
            `;
            option.addEventListener('click', () => { addProduct(product); beepScan(); });
            resultsContainer.appendChild(option);
        });
        resultsContainer.classList.remove('d-none');
    };

    // ── Ticket print markup ─────────────────────────────
    const buildPrintMarkup = () => {
        const printedAt = new Date().toLocaleString('es-AR');
        const rows = Array.from(items.values()).map((item) => {
            const amount = Number(item.quantity) * Number(item.unit_price);
            return `<div class="ticket-line"><div class="ticket-name">${item.sku} ${item.name}</div><div class="ticket-meta">${item.brand || 'Sin marca'}</div><div class="ticket-row"><span>${formatMoney(item.quantity)} x ${formatMoney(item.unit_price)}</span><strong>${formatMoney(amount)}</strong></div></div>`;
        }).join('');
        const change = Math.max(0, (parseFloat(paidAmount.value) || 0) - totalAmount());
        return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Ticket 80mm</title><style>@page{size:80mm auto;margin:4mm;}*{box-sizing:border-box;}body{margin:0;font-family:"Courier New",monospace;background:#f7f4ef;color:#111;}.preview-shell{min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:18px;}.preview-card{width:100%;max-width:360px;}.ticket{width:80mm;max-width:100%;background:#fff;margin:0 auto;padding:5mm 4mm;border-radius:12px;box-shadow:0 16px 40px rgba(0,0,0,.14);}.ticket-center{text-align:center;}.ticket-small{font-size:11px;color:#555;}.ticket-line{border-bottom:1px dashed #bbb;padding:6px 0;}.ticket-name{font-size:12px;font-weight:700;margin-bottom:2px;}.ticket-meta{font-size:11px;color:#666;margin-bottom:4px;}.ticket-row{display:flex;justify-content:space-between;gap:8px;font-size:12px;}.ticket-total{border-top:1px solid #000;margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;font-size:15px;font-weight:700;}.preview-actions{display:flex;justify-content:center;gap:10px;margin-top:14px;}.preview-actions button{border:1px solid #1f2328;border-radius:10px;background:#fff;padding:9px 14px;cursor:pointer;}@media print{body{background:#fff;}.preview-actions{display:none;}.ticket{box-shadow:none;border-radius:0;margin:0;width:72mm;}.preview-shell{padding:0;}}</style></head><body><div class="preview-shell"><div class="preview-card"><div class="ticket"><div class="ticket-center"><div><strong><?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?></strong></div><div class="ticket-small">${printedAt}</div><div class="ticket-small">${currencyField.options[currencyField.selectedIndex].text}</div><div class="ticket-small">Referencia: ${referenceField.value}</div></div>${rows}<div class="ticket-total"><span>TOTAL</span><span>${totalLabel.textContent}</span></div><div class="ticket-small" style="margin-top:8px;">Pago: ${paymentMethod.options[paymentMethod.selectedIndex].text}</div><div class="ticket-small">Cobrado: ${formatMoney(paidAmount.value||0)}</div>${change > 0 ? '<div class="ticket-small" style="font-weight:700;color:#198754;">Vuelto: ' + formatMoney(change) + '</div>' : ''}</div><div class="preview-actions"><button type="button" onclick="window.print()">Imprimir</button><button type="button" onclick="window.close()">Cerrar</button></div></div></div></body></html>`;
    };

    // ── Event listeners ─────────────────────────────────
    searchField.addEventListener('input', () => renderResults(matchingProducts(searchField.value)));
    warehouseField?.addEventListener('change', () => renderResults(matchingProducts(searchField.value)));

    document.addEventListener('click', (event) => {
        if (!resultsContainer.contains(event.target) && event.target !== searchField) {
            resultsContainer.classList.add('d-none');
        }
    });

    // ── CSRF token helpers ────────────────────────────────
    const csrfName = '<?= csrf_token() ?>';
    const getCsrfField = () => form.querySelector('input[name="' + csrfName + '"]');

    const refreshCsrfToken = (newToken) => {
        const field = getCsrfField();
        if (field && newToken) {
            field.value = newToken;
        }
    };

    // ── Continuous flow: AJAX submit ────────────────────
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (items.size === 0) {
            beepError();
            showToast('Agrega al menos un producto', 'exclamation-triangle-fill');
            return;
        }
        syncHiddenInputs();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(res => {
            // CSRF failure — reload to get a fresh token
            if (res.status === 403) {
                beepError();
                showToast('Sesion de seguridad expirada, recargando...', 'shield-exclamation');
                setTimeout(() => window.location.reload(), 1500);
                return;
            }
            if (!res.ok) {
                return res.json().then(data => { throw new Error(data.message || 'HTTP ' + res.status); });
            }
            return res.json();
        })
        .then(data => {
            if (!data) return;

            // Refresh the CSRF token for the next submit
            if (data.csrf_token) {
                refreshCsrfToken(data.csrf_token);
            }

            if (data.status === 'ok') {
                beepConfirm();
                showToast('Venta registrada ✓ ' + (data.sale_number || referenceField.value));
                items.clear();
                renderTicket();
                searchField.value = '';
                searchField.focus();
            } else {
                throw new Error(data.message || 'Error desconocido');
            }
        })
        .catch(err => {
            beepError();
            showToast('Error al registrar: ' + err.message, 'x-circle-fill');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
        });
    });

    printButton.addEventListener('click', () => {
        if (items.size === 0) {
            showToast('Agrega productos antes de imprimir', 'exclamation-triangle-fill');
            return;
        }
        const popup = window.open('', 'codex-kiosk-ticket', 'width=460,height=820');
        if (!popup) return;
        popup.document.open();
        popup.document.write(buildPrintMarkup());
        popup.document.close();
        popup.focus();
    });

    cancelButton.addEventListener('click', () => {
        if (items.size > 0 && !confirm('¿Cancelar la factura actual?')) return;
        items.clear();
        renderTicket();
        searchField.value = '';
        searchField.focus();
        showToast('Ticket cancelado', 'slash-circle');
    });

    // ── Keyboard shortcuts ──────────────────────────────
    document.addEventListener('keydown', (event) => {
        // Don't intercept if user is in a text input that isn't the search
        const active = document.activeElement;
        const isInput = active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.id !== 'kiosk-search' && active.type !== 'number'));
        if (isInput) return;

        switch (event.key) {
            case 'F2':
                event.preventDefault();
                submitBtn.click();
                break;
            case 'F4':
                event.preventDefault();
                cancelButton.click();
                break;
            case 'F5':
                event.preventDefault();
                printButton.click();
                break;
            case 'F9':
                event.preventDefault();
                const opts = paymentMethod.options;
                paymentMethod.selectedIndex = (paymentMethod.selectedIndex + 1) % opts.length;
                showToast('Pago: ' + opts[paymentMethod.selectedIndex].text, 'credit-card');
                break;
            case 'Escape':
                searchField.value = '';
                renderResults([]);
                searchField.focus();
                break;
        }
    });

    renderTicket();
    searchField.focus();
})();
</script>
<?= $this->endSection() ?>
