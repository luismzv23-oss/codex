<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$consumerFinalId = '';
foreach (($customers ?? []) as $c) {
    if ($c['name'] === 'Consumidor Final') {
        $consumerFinalId = $c['id'];
        break;
    }
}
$productCatalog = array_values(array_map(static function (array $product): array {
    return [
        'id' => $product['id'],
        'sku' => $product['sku'],
        'name' => $product['name'],
        'brand' => $product['brand'] ?? '',
        'unit' => $product['unit'] ?? 'unidad',
        'price' => (float) ($product['sale_price'] ?? 0),
        'stocks' => $product['stocks'] ?? [],
        'image' => $product['image'] ?? null,
    ];
}, $products ?? []));
?>
<div id="codex-kiosk-toast" class="codex-kiosk-toast"><i class="bi bi-check-circle-fill"></i><span></span></div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
    <div class="d-flex align-items-center gap-3">
        <h1 class="h2 mb-0">Facturacion kiosco</h1>
        <?php if (!empty($cashSession)): ?>
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill px-3 py-2">
                <i class="bi bi-unlock-fill me-1"></i>
                <?= esc($cashSession['register_name'] ?? 'Caja Kiosco') ?>
                <span class="text-body-secondary ms-1">· Abierta
                    <?= esc(date('H:i', strtotime($cashSession['opened_at'] ?? 'now'))) ?></span>
            </span>
        <?php else: ?>
            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle rounded-pill px-3 py-2">
                <i class="bi bi-lock-fill me-1"></i> Sin caja activa
            </span>
        <?php endif; ?>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= site_url('ventas/pos' . (!empty($companyId) ? '?company_id=' . $companyId : '')) ?>"
            class="btn btn-outline-dark icon-btn" title="Venta POS" aria-label="Venta POS"><i
                class="bi bi-display"></i></a>
        <a href="<?= site_url('ventas/kiosco' . (!empty($companyId) ? '?company_id=' . $companyId : '')) ?>"
            class="btn btn-dark icon-btn" title="Venta kiosco" aria-label="Venta kiosco"><i class="bi bi-shop"></i></a>
        <a href="<?= site_url('ventas' . (!empty($companyId) ? '?company_id=' . $companyId : '')) ?>"
            class="btn btn-outline-dark icon-btn" title="Volver a Ventas" aria-label="Volver a Ventas"><i
                class="bi bi-arrow-left"></i></a>

    </div>
</div>
<p class="text-secondary mb-4">Pantalla continua de emision rapida · Escanea codigo de barras o busca por nombre
    · <kbd>F2</kbd> Cobrar · <kbd>F4</kbd> Cancelar · <kbd>F5</kbd> Imprimir</p>




<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 p-lg-5">
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" id="kiosk-form"
            class="row g-4">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (!empty($companyId)): ?><input type="hidden" name="company_id"
                    value="<?= esc($companyId) ?>"><?php endif; ?>
            <input type="hidden" name="pos_mode" value="1">
            <input type="hidden" name="notes" value="Ticket kiosco">

            <div class="col-lg-3">
                <label class="form-label">Deposito</label>
                <select name="warehouse_id" class="form-select" required>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>"><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Moneda</label>
                <select name="currency_code" class="form-select" id="kiosk-currency" required>
                    <?php foreach ($currencyOptions as $code => $label): ?>
                        <option value="<?= esc($code) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Documento</label>
                <input type="text" class="form-control" id="kiosk-document-display"
                    value="<?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?>" readonly>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Cliente</label>
                <div class="input-group">
                    <input type="text" id="kiosk-customer-name" class="form-control" value="Consumidor Final" readonly>
                    <input type="hidden" name="customer_id" id="kiosk-customer-id" value="<?= esc($consumerFinalId) ?>">
                    <button type="button" class="btn btn-outline-dark" id="open-kiosk-customer-search"
                        title="Buscar cliente" aria-label="Buscar cliente"><i class="bi bi-search"></i></button>
                    <a href="<?= site_url('ventas/clientes/nuevo' . (!empty($companyId) ? '?company_id=' . $companyId : '')) ?>"
                        class="btn btn-outline-dark" data-popup="true" data-popup-title="Cliente"
                        data-popup-subtitle="Alta rapida de cliente para ventas." title="Nuevo cliente"
                        aria-label="Nuevo cliente" id="kiosk-new-customer-btn"><i class="bi bi-person-plus"></i></a>
                    <button type="button" class="btn btn-outline-danger d-none" id="clear-kiosk-customer"
                        title="Quitar cliente" aria-label="Quitar cliente"><i class="bi bi-x-lg"></i></button>
                </div>
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
                                <input type="text" id="kiosk-search" class="form-control"
                                    placeholder="Escanea o escribe para buscar" autocomplete="off">
                                <input type="hidden" id="kiosk-selected-product-id">
                                <div id="kiosk-search-results"
                                    class="list-group position-absolute start-0 end-0 mt-2 shadow-sm d-none"
                                    style="z-index: 20; max-height: 320px; overflow:auto;"></div>
                            </div>

                            <div class="small text-secondary mt-3">
                                Coincidencias: <span class="fw-semibold" id="kiosk-results-count">0</span>
                                <span class="ms-2" id="kiosk-scan-indicator" style="display:none;">📡 <strong>Barcode
                                        detectado</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-7">
                        <div class="border rounded-4 p-3 p-lg-4 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h2 class="h5 mb-1">Factura del cliente</h2>
                                    <p class="text-secondary mb-0">Detalle del ticket con cantidad y precio unitario de
                                        venta.</p>
                                </div>
                                <div class="text-end">
                                    <div class="small text-secondary" id="kiosk-tax-breakdown-label"
                                        style="display:none;"></div>
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
                                                <th style="width: 90px;">Cant.</th>
                                                <th style="width: 110px;">Precio</th>
                                                <th style="width: 90px;">Desc. %</th>
                                                <th style="width: 120px;">Total</th>
                                                <th class="text-end" style="width: 56px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="kiosk-ticket-body">
                                            <tr id="kiosk-empty-row">
                                                <td colspan="6" class="text-secondary py-4">Todavia no agregaste
                                                    productos a la factura.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-3">
                                    <label class="form-label">Pago</label>
                                    <select name="payments[0][payment_method]" class="form-select"
                                        id="kiosk-payment-method">
                                        <option value="cash">Efectivo</option>
                                        <option value="card">Tarjeta</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="mixed">Mixto</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Monto cobrado</label>
                                    <input type="number" step="0.01" min="0" name="payments[0][amount]"
                                        class="form-control" id="kiosk-paid-amount" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Vuelto</label>
                                    <div class="codex-kiosk-change" id="kiosk-change">$0,00</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Referencia</label>
                                    <input type="text" name="payments[0][reference]" class="form-control"
                                        id="kiosk-reference" value="<?= esc($documentReference ?? '') ?>" readonly>
                                </div>
                            </div>

                            <div id="kiosk-hidden-items"></div>

                            <div class="col-12 pt-3">
                                <div class="d-flex gap-3 align-items-center flex-wrap">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="kiosk_emit_type"
                                            id="kiosk-emit-ticket" value="ticket" checked>
                                        <label class="form-check-label" for="kiosk-emit-ticket">Ticket</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="kiosk_emit_type"
                                            id="kiosk-emit-factura" value="factura">
                                        <label class="form-check-label" for="kiosk-emit-factura">Factura</label>
                                    </div>
                                    <div id="kiosk-factura-options" class="d-none">
                                        <select id="kiosk-factura-doc-type" class="form-select form-select-sm"
                                            style="min-width:160px;">
                                            <?php foreach (($invoiceDocumentTypes ?? []) as $dt): ?>
                                                <option value="<?= esc($dt['id']) ?>" <?= !empty($dt['is_default']) ? 'selected' : '' ?>><?= esc($dt['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                    </div>
                                </div>
                                <input type="hidden" name="authorize_arca" id="kiosk-authorize-arca" value="0">
                                <input type="hidden" name="factura_document_type_id" id="kiosk-factura-doc-type-hidden"
                                    value="">
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-4">
                                <button type="button" class="btn btn-outline-dark icon-btn" id="kiosk-print-preview"
                                    title="Imprimir factura (F5)" aria-label="Imprimir factura"><i
                                        class="bi bi-printer"></i></button>
                                <button type="submit" class="btn btn-dark icon-btn" id="kiosk-submit-btn"
                                    title="Registrar factura (F2)" aria-label="Registrar factura"><i
                                        class="bi bi-check-lg"></i></button>
                                <button type="button" class="btn btn-outline-dark icon-btn" id="kiosk-cancel-button"
                                    title="Cancelar factura (F4)" aria-label="Cancelar factura"><i
                                        class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="modal fade" id="kioskCustomerSearchModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header">
                        <div>
                            <h2 class="h5 mb-1">Buscar cliente</h2>
                            <p class="text-secondary mb-0">Busca por nombre o documento y selecciona el cliente.</p>
                        </div>
                        <button type="button" class="btn btn-outline-dark icon-btn" data-bs-dismiss="modal"
                            aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label">Nombre / Documento</label>
                            <input type="text" id="kiosk-customer-search" class="form-control"
                                placeholder="Escribe el nombre o documento para buscar..." autocomplete="off">
                        </div>
                        <div class="small text-secondary mb-3">
                            Coincidencias: <span class="fw-semibold" id="kiosk-customer-results-count">0</span>
                        </div>
                        <div id="kiosk-customer-search-results" class="list-group border rounded-4 overflow-auto"
                            style="max-height: 360px;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-dark icon-btn" data-bs-dismiss="modal"
                            title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const catalog = <?= json_encode($productCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const customers = <?= json_encode($customers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const consumerFinalId = '<?= esc($consumerFinalId) ?>';
        const ticketSettings = <?= json_encode($ticketSettings ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const companyName = <?= json_encode($company['name'] ?? '') ?>;
        const companyLegalName = <?= json_encode($company['legal_name'] ?? '') ?>;
        const companyTaxId = <?= json_encode($company['tax_id'] ?? '') ?>;
        const userName = <?= json_encode(auth_user()['name'] ?? '') ?>;
        const defaultTax = <?= json_encode($defaultTax ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const sequences = <?= json_encode($sequences ?? []) ?>;
        const kioskDocType = <?= json_encode($documentType) ?>;


        const searchField = document.getElementById('kiosk-search');
        const resultsContainer = document.getElementById('kiosk-search-results');
        const resultsCount = document.getElementById('kiosk-results-count');
        const scanIndicator = document.getElementById('kiosk-scan-indicator');
        const ticketBody = document.getElementById('kiosk-ticket-body');
        const emptyRow = document.getElementById('kiosk-empty-row');
        const totalLabel = document.getElementById('kiosk-total');
        const taxBreakdownLabel = document.getElementById('kiosk-tax-breakdown-label');
        const hiddenItems = document.getElementById('kiosk-hidden-items');

        const paidAmount = document.getElementById('kiosk-paid-amount');
        const changeLabel = document.getElementById('kiosk-change');
        const paymentMethod = document.getElementById('kiosk-payment-method');
        const currencyField = document.getElementById('kiosk-currency');
        const referenceField = document.getElementById('kiosk-reference');
        const form = document.getElementById('kiosk-form');
        const warehouseField = form.querySelector('select[name="warehouse_id"]');
        const printButton = document.getElementById('kiosk-print-preview');
        const authorizeArcaField = document.getElementById('kiosk-authorize-arca');
        const facturaDocTypeHidden = document.getElementById('kiosk-factura-doc-type-hidden');
        const facturaDocTypeSelect = document.getElementById('kiosk-factura-doc-type');
        const facturaOptions = document.getElementById('kiosk-factura-options');

        const updateReferenceField = () => {
            const emitType = document.querySelector('input[name="kiosk_emit_type"]:checked')?.value || 'ticket';
            if (emitType === 'factura') {
                const docTypeId = facturaDocTypeSelect ? facturaDocTypeSelect.value : '';
                referenceField.value = sequences[docTypeId] || '';
            } else {
                referenceField.value = kioskDocType ? (sequences[kioskDocType.id] || '') : '';
            }
        };

        const syncFacturaDocType = () => {
            facturaDocTypeHidden.value = facturaDocTypeSelect ? facturaDocTypeSelect.value : '';
            updateReferenceField();
        };

        if (facturaDocTypeSelect) {
            facturaDocTypeSelect.addEventListener('change', syncFacturaDocType);
        }

        document.querySelectorAll('input[name="kiosk_emit_type"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                if (radio.value === 'factura') {
                    facturaOptions.classList.remove('d-none');
                    authorizeArcaField.value = '1';
                    syncFacturaDocType();
                } else {
                    facturaOptions.classList.add('d-none');
                    authorizeArcaField.value = '0';
                    facturaDocTypeHidden.value = '';
                    updateReferenceField();
                }
            });
        });

        const cancelButton = document.getElementById('kiosk-cancel-button');
        const submitBtn = document.getElementById('kiosk-submit-btn');
        const toastEl = document.getElementById('codex-kiosk-toast');
        const items = new Map();

        // Customer elements
        const kioskCustomerName = document.getElementById('kiosk-customer-name');
        const kioskCustomerId = document.getElementById('kiosk-customer-id');
        const openCustomerSearch = document.getElementById('open-kiosk-customer-search');
        const clearCustomerBtn = document.getElementById('clear-kiosk-customer');
        const customerSearchModalEl = document.getElementById('kioskCustomerSearchModal');
        const customerSearchInput = document.getElementById('kiosk-customer-search');
        const customerResultsList = document.getElementById('kiosk-customer-search-results');
        const customerResultsCount = document.getElementById('kiosk-customer-results-count');
        const kioskDocumentDisplay = document.getElementById('kiosk-document-display');
        const defaultKioskDocLabel = '<?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?>';

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
                const fields = {
                    product_id: item.product_id || item.id,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    discount_rate: item.discount_rate || 0,
                    tax_id: item.tax_id || (defaultTax ? defaultTax.id : '')
                };
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

        const lineGrossAmount = (item) => {
            const base = Number(item.quantity) * Number(item.unit_price);
            const discount = base * (Number(item.discount_rate || 0) / 100);
            return Math.max(0, base - discount);
        };

        const totalAmount = () => Array.from(items.values()).reduce((carry, item) => {
            return carry + lineGrossAmount(item);
        }, 0);

        const subtotalAmount = () => Array.from(items.values()).reduce((carry, item) => {
            const gross = lineGrossAmount(item);
            const taxRate = item.tax_rate !== undefined ? Number(item.tax_rate) : (defaultTax ? Number(defaultTax.rate || 0) : 0);
            const net = taxRate > 0 ? gross / (1 + (taxRate / 100)) : gross;
            return carry + net;
        }, 0);

        const taxTotalAmount = () => totalAmount() - subtotalAmount();



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
                const base = Number(item.quantity) * Number(item.unit_price);
                const discountAmount = base * (Number(item.discount_rate || 0) / 100);
                const lineTotal = base - discountAmount;
                const row = document.createElement('tr');
                row.innerHTML = `
                <td>
                    <div class="fw-semibold">${item.sku} - ${item.name}</div>
                    <div class="small text-secondary">${item.brand || 'Sin marca'}</div>
                </td>
                <td>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="form-control kiosk-qty"
                        data-id="${item.id}"
                        value="${Number(item.quantity).toFixed(2)}"
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
                <td>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        class="form-control kiosk-discount"
                        data-id="${item.id}"
                        value="${Number(item.discount_rate || 0).toFixed(2)}"
                    >
                </td>
                <td class="fw-semibold line-total">${formatMoney(lineTotal)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-outline-dark icon-btn kiosk-remove" data-id="${item.id}" title="Quitar" aria-label="Quitar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            `;
                ticketBody.appendChild(row);
            });

            const subtotal = subtotalAmount();
            const taxTotal = taxTotalAmount();
            const total = totalAmount();

            totalLabel.textContent = formatMoney(total);

            if (taxBreakdownLabel) {
                if (taxTotal > 0 && defaultTax) {
                    taxBreakdownLabel.textContent = `Neto: $${formatMoney(subtotal)} | ${defaultTax.name} (${Number(defaultTax.rate).toFixed(0)}%): $${formatMoney(taxTotal)}`;
                    taxBreakdownLabel.style.display = 'block';
                } else {
                    taxBreakdownLabel.style.display = 'none';
                }
            }

            paidAmount.value = Number(total).toFixed(2);
            updateChange();
            syncHiddenInputs();

            ticketBody.querySelectorAll('.kiosk-qty').forEach((field) => {
                field.addEventListener('change', () => {
                    const item = items.get(field.dataset.id);
                    if (!item) return;
                    item.quantity = Math.max(0.01, Number(field.value || 0));
                    renderTicket();
                });
            });

            ticketBody.querySelectorAll('.kiosk-discount').forEach((field) => {
                field.addEventListener('change', () => {
                    const item = items.get(field.dataset.id);
                    if (!item) return;
                    item.discount_rate = Math.max(0, Math.min(100, Number(field.value || 0)));
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
                    discount_rate: 0,
                    tax_id: defaultTax ? defaultTax.id : '',
                    tax_rate: defaultTax ? Number(defaultTax.rate || 0) : 0,
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
                    <div class="d-flex gap-3 align-items-start text-start">
                        ${product.image
                        ? `<img src="/uploads/products/${product.image}" style="width:40px;height:40px;object-fit:cover;" class="rounded flex-shrink-0">`
                        : `<span class="d-flex align-items-center justify-content-center rounded bg-light text-secondary flex-shrink-0" style="width:40px;height:40px;"><i class="bi bi-box"></i></span>`
                    }
                        <div>
                            <div class="fw-semibold">${product.sku} - ${product.name}</div>
                            <div class="small text-secondary">${product.brand || 'Sin marca'}</div>
                        </div>
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
            const paperWidth = ticketSettings.ticket_paper_width || '80mm';

            const fontFamilyOption = ticketSettings.ticket_font_family || 'Courier';
            let fontFamilyStyle = '"Courier New", Courier, monospace';
            let fontWeightStyle = 'normal';
            if (fontFamilyOption === 'Helvetica 75 Bold') {
                fontFamilyStyle = '"Helvetica 75 Bold", "Helvetica Neue", Helvetica, Arial, sans-serif';
                fontWeightStyle = 'bold';
            } else if (fontFamilyOption === 'Helvetica') {
                fontFamilyStyle = '"Helvetica Neue", Helvetica, Arial, sans-serif';
            } else if (fontFamilyOption === 'DejaVu Sans') {
                fontFamilyStyle = '"DejaVu Sans", sans-serif';
            } else if (fontFamilyOption === 'DejaVu Serif') {
                fontFamilyStyle = '"DejaVu Serif", serif';
            } else if (fontFamilyOption === 'Times-Roman') {
                fontFamilyStyle = '"Times New Roman", Times, serif';
            }

            const topLeftText = ticketSettings.ticket_custom_text_top_left || '';
            const topRightText = ticketSettings.ticket_custom_text_top_right || '';
            const boldTopLeft = Number(ticketSettings.ticket_bold_top_left) === 1;
            const boldTopRight = Number(ticketSettings.ticket_bold_top_right) === 1;

            const companySubtitle = ticketSettings.ticket_company_subtitle || '';
            const companyAddress = ticketSettings.ticket_company_address || '';
            const companyPhone = ticketSettings.ticket_company_phone || '';

            const rows = Array.from(items.values()).map((item) => {
                const base = Number(item.quantity) * Number(item.unit_price);
                const discountAmount = base * (Number(item.discount_rate || 0) / 100);
                const amount = base - discountAmount;
                const discountText = item.discount_rate > 0 ? ` <span style="font-size:10px">- ${Number(item.discount_rate).toFixed(0)}%</span>` : '';

                const skuPart = Number(ticketSettings.ticket_show_sku) === 1 ? `[${item.sku}] ` : '';
                const brandPart = (Number(ticketSettings.ticket_show_brand) === 1 && item.brand)
                    ? `<div class="ticket-meta">${item.brand}</div>`
                    : '';

                const showBreakdown = Number(ticketSettings.ticket_show_item_breakdown) === 1;
                const breakdownHtml = showBreakdown
                    ? `<span>${formatMoney(item.quantity)} x ${formatMoney(item.unit_price)}${discountText}</span>`
                    : `<span>Cant: ${Number(item.quantity)}</span>`;

                return `
                    <div class="ticket-line">
                        <div class="ticket-name">${skuPart}${item.name}</div>
                        ${brandPart}
                        <div class="ticket-row">
                            ${breakdownHtml}
                            <strong>${formatMoney(amount)}</strong>
                        </div>
                    </div>
                `;
            }).join('');

            const change = Math.max(0, (parseFloat(paidAmount.value) || 0) - totalAmount());

            const headerTitle = ticketSettings.ticket_header_title || companyLegalName || companyName;

            const showCustomer = Number(ticketSettings.ticket_show_customer) === 1;
            const customerHtml = showCustomer
                ? `
                    <div class="ticket-small" style="margin-top:6px; border-top:1px dashed #000; padding-top:4px; text-align:left;">
                        <strong>Cliente:</strong> ${kioskCustomerName.value}<br>
                        ${kioskDocumentDisplay ? `<strong>Doc:</strong> ${kioskDocumentDisplay.value}` : ''}
                    </div>
                `
                : '';

            const showUser = Number(ticketSettings.ticket_show_user) === 1;
            const userHtml = showUser
                ? `<div class="ticket-small" style="text-align:left;"><strong>Vendedor:</strong> ${userName}</div>`
                : '';

            const footerHtml = ticketSettings.ticket_footer_notes
                ? `
                    <div class="ticket-small" style="margin-top:10px; border-top:1px dashed #000; padding-top:6px; text-align:center; white-space:pre-wrap;">
                        ${ticketSettings.ticket_footer_notes}
                    </div>
                `
                : '';

            const printableWidth = paperWidth === '58mm' ? '50mm' : '72mm';

            return `<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket ${paperWidth}</title>
    <style>
        @page {
            size: ${paperWidth} auto;
            margin: 3mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: ${fontFamilyStyle};
            font-weight: ${fontWeightStyle};
            background: #f7f4ef;
            color: #000;
        }
        .preview-shell {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 18px;
        }
        .preview-card {
            width: 100%;
            max-width: 360px;
        }
        .ticket {
            width: ${paperWidth};
            max-width: 100%;
            background: #fff;
            margin: 0 auto;
            padding: 5mm 4mm;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(0,0,0,.14);
            color: #000;
        }
        .ticket-center {
            text-align: center;
        }
        .ticket-small {
            font-size: 11px;
            color: #000;
            line-height: 1.35;
        }
        .ticket-line {
            border-bottom: 1px dashed #000;
            padding: 6px 0;
        }
        .ticket-name {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 2px;
            word-break: break-word;
        }
        .ticket-meta {
            font-size: 11px;
            color: #000;
            margin-bottom: 4px;
        }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
        }
        .ticket-total {
            border-top: 1px solid #000;
            margin-top: 8px;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 15px;
            font-weight: 700;
        }
        .preview-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 14px;
        }
        .preview-actions button {
            border: 1px solid #1f2328;
            border-radius: 10px;
            background: #fff;
            padding: 9px 14px;
            cursor: pointer;
        }
        @media print {
            body {
                background: #fff;
                color: #000 !important;
            }
            .ticket, .ticket * {
                color: #000 !important;
                background: #fff !important;
            }
            .preview-actions {
                display: none;
            }
            .ticket {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                width: ${printableWidth};
            }
            .preview-shell {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="preview-shell">
        <div class="preview-card">
            <div class="ticket">
                <div class="ticket-center">
                    <div style="font-size:14px; font-weight:700; text-transform:uppercase; margin-bottom:4px;">${headerTitle}</div>
                    ${companySubtitle ? `<div style="font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:4px;">${companySubtitle}</div>` : ''}
                    ${companyTaxId ? `<div class="ticket-small">CUIT: ${companyTaxId}</div>` : ''}
                    ${companyAddress ? `<div class="ticket-small">${companyAddress}</div>` : ''}
                    ${companyPhone ? `<div class="ticket-small">${companyPhone}</div>` : ''}
                    ${(topLeftText || topRightText) ? `
                    <div style="display: flex; justify-content: space-between; font-size: 11px; margin-top: 4px; padding-bottom: 4px; border-bottom: 1px dashed #000;">
                        <span style="font-weight: ${boldTopLeft ? 'bold' : 'normal'}; text-align: left; white-space: pre-line;">${topLeftText}</span>
                        <span style="font-weight: ${boldTopRight ? 'bold' : 'normal'}; text-align: right; white-space: pre-line;">${topRightText}</span>
                    </div>` : `
                    <div style="margin:4px 0; border-bottom:1px dashed #000;"></div>
                    `}
                    <div><strong><?= esc($settings['kiosk_document_label'] ?? 'Ticket Consumidor Final') ?></strong></div>
                    <div class="ticket-small">${printedAt}</div>
                    <div class="ticket-small">${currencyField.options[currencyField.selectedIndex].text}</div>
                    <div class="ticket-small">Referencia: ${referenceField.value}</div>
                </div>
                
                ${rows}
                
                ${taxTotalAmount() > 0 ? `
                <div class="ticket-small" style="margin-top:6px; text-align:right; border-top:1px dashed #000; padding-top:4px;">
                    <span>Neto: $${formatMoney(subtotalAmount())}</span><br>
                    <span>${defaultTax ? defaultTax.name : 'IVA'} (${defaultTax ? Number(defaultTax.rate).toFixed(0) : 21}%): $${formatMoney(taxTotalAmount())}</span>
                </div>
                ` : ''}
                <div class="ticket-total">
                    <span>TOTAL</span>
                    <span>${totalLabel.textContent}</span>
                </div>

                
                <div class="ticket-small" style="margin-top:8px; text-align:left;"><strong>Pago:</strong> ${paymentMethod.options[paymentMethod.selectedIndex].text}</div>
                <div class="ticket-small" style="text-align:left;"><strong>Cobrado:</strong> ${formatMoney(paidAmount.value || 0)}</div>
                ${change > 0 ? '<div class="ticket-small" style="font-weight:700; color:#198754; text-align:left;"><strong>Vuelto:</strong> ' + formatMoney(change) + '</div>' : ''}
                
                ${customerHtml}
                ${userHtml}
                ${footerHtml}
            </div>
            <div class="preview-actions">
                <button type="button" onclick="window.print()">Imprimir</button>
                <button type="button" onclick="window.close()">Cerrar</button>
            </div>
        </div>
    </div>
</body>
</html>`;
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
                        let toastMsg = 'Venta registrada ✓ ' + (data.sale_number || referenceField.value);
                        if (data.arca_cae) {
                            toastMsg += ' · CAE: ' + data.arca_cae;
                        }
                        showToast(toastMsg);
                        items.clear();
                        renderTicket();
                        kioskCustomerId.value = consumerFinalId;
                        kioskCustomerName.value = 'Consumidor Final';
                        if (kioskDocumentDisplay) kioskDocumentDisplay.value = defaultKioskDocLabel;
                        clearCustomerBtn.classList.add('d-none');
                        // Reset emit type to Ticket
                        const ticketRadio = document.getElementById('kiosk-emit-ticket');
                        if (ticketRadio) { ticketRadio.checked = true; ticketRadio.dispatchEvent(new Event('change')); }
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
            kioskCustomerId.value = consumerFinalId;
            kioskCustomerName.value = 'Consumidor Final';
            if (kioskDocumentDisplay) kioskDocumentDisplay.value = defaultKioskDocLabel;
            clearCustomerBtn.classList.add('d-none');
            searchField.value = '';
            searchField.focus();
            showToast('Ticket cancelado', 'slash-circle');
        });

        // ── Customer Search Logic ────────────────────────────
        const resolveCustomerSearchModal = () => {
            if (!window.bootstrap || !window.bootstrap.Modal) return null;
            return window.bootstrap.Modal.getOrCreateInstance(customerSearchModalEl);
        };

        const matchingCustomers = (term) => {
            const normalized = term.trim().toLowerCase();
            if (normalized === '') return [];
            return customers.filter((customer) => {
                const haystack = `${customer.name} ${customer.document_number || ''} ${customer.email || ''} ${customer.phone || ''}`.toLowerCase();
                return haystack.includes(normalized);
            }).slice(0, 12);
        };

        const renderCustomerResults = (results) => {
            customerResultsList.innerHTML = '';
            customerResultsCount.textContent = String(results.length);
            if (results.length === 0) return;

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
                    kioskCustomerId.value = customer.id;
                    kioskCustomerName.value = customer.name;
                    if (customer.id !== consumerFinalId && customer.name !== 'Consumidor Final') {
                        if (kioskDocumentDisplay) kioskDocumentDisplay.value = (customer.document_type || 'DOC') + ' ' + (customer.document_number || '');
                        clearCustomerBtn.classList.remove('d-none');
                    } else {
                        if (kioskDocumentDisplay) kioskDocumentDisplay.value = defaultKioskDocLabel;
                        clearCustomerBtn.classList.add('d-none');
                    }
                    beepConfirm();
                    showToast('Cliente seleccionado: ' + customer.name);
                    resolveCustomerSearchModal()?.hide();
                });
                customerResultsList.appendChild(option);
            });
        };

        openCustomerSearch.addEventListener('click', () => {
            const searchModal = resolveCustomerSearchModal();
            if (!searchModal) return;
            customerSearchInput.value = '';
            renderCustomerResults([]);
            customerResultsCount.textContent = '0';
            searchModal.show();
            setTimeout(() => customerSearchInput.focus(), 150);
        });

        customerSearchInput.addEventListener('input', () => renderCustomerResults(matchingCustomers(customerSearchInput.value)));
        customerSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                const first = customerResultsList.querySelector('button');
                if (first) first.click();
            }
        });

        clearCustomerBtn.addEventListener('click', () => {
            kioskCustomerId.value = consumerFinalId;
            kioskCustomerName.value = 'Consumidor Final';
            if (kioskDocumentDisplay) kioskDocumentDisplay.value = defaultKioskDocLabel;
            clearCustomerBtn.classList.add('d-none');
            showToast('Cliente restablecido a Consumidor Final');
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
                kioskCustomerId.value = newCustomer.id;
                kioskCustomerName.value = newCustomer.name;
                if (kioskDocumentDisplay) kioskDocumentDisplay.value = (newCustomer.document_type || 'DOC') + ' ' + (newCustomer.document_number || '');
                clearCustomerBtn.classList.remove('d-none');
                showToast('Cliente seleccionado: ' + newCustomer.name);
            }
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

        updateReferenceField();
        renderTicket();
        searchField.focus();
    })();
</script>
<?= $this->endSection() ?>