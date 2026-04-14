<?php
$context = $context ?? ['canManage' => false];
$companies = $companies ?? [];
$selectedCompanyId = $selectedCompanyId ?? '';
?>
<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1"><?= empty($sale) ? 'Venta nueva' : 'Editar venta' ?></h1>
        <p class="text-secondary mb-0">Crea el borrador, selecciona productos con stock.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('ventas') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('ventas/reportes' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Reportes</a>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('ventas/pos' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark">POS</a>
            <a href="<?= site_url('ventas/kiosco' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Kiosco</a>
            <a href="<?= site_url('ventas/listas-precio/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Lista de precios" data-popup-subtitle="Configurar precios comerciales por producto.">Lista de precios</a>
            <a href="<?= site_url('ventas/promociones/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Promocion" data-popup-subtitle="Crear promociones comerciales activas.">Promociones</a>
        <?php endif; ?>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('ventas/clientes/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Cliente" data-popup-subtitle="Alta rapida de cliente para ventas.">Nuevo cliente</a>
            <!--<a href="<?= site_url('ventas/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark" data-popup="true" data-popup-title="Venta" data-popup-subtitle="Crear venta integrada con inventario.">Nueva venta</a>-->
        <?php endif; ?>
    </div>
</div>
<?php
$sale = $sale ?? null;
$saleItems = $saleItems ?? [];
$salePayments = $salePayments ?? [];
$sourceSale = $sourceSale ?? null;
$currencyCode = $currencyCode ?? 'ARS';
$defaultWarehouseId = old('warehouse_id', $sale['warehouse_id'] ?? (($warehouses[0]['id'] ?? '')));
$defaultDueDate = old('due_date', ! empty($sale['due_date']) ? date('Y-m-d\TH:i', strtotime($sale['due_date'])) : date('Y-m-d\TH:i', strtotime('+24 hours')));
$productCatalog = array_values(array_map(static function (array $product): array {
    return [
        'id' => $product['id'],
        'sku' => $product['sku'],
        'name' => $product['name'],
        'brand' => $product['brand'] ?? '',
        'unit' => $product['unit'] ?? 'unidad',
        'sale_price' => (float) ($product['sale_price'] ?? 0),
        'stocks' => $product['stocks'] ?? [],
    ];
}, $products ?? []));
$taxCatalog = array_values(array_map(static function (array $tax): array {
    return ['id' => $tax['id'], 'name' => $tax['name'], 'rate' => (float) ($tax['rate'] ?? 0)];
}, $taxes ?? []));
?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-4" id="sales-form">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <?php if (! empty($sourceSale['id'])): ?><input type="hidden" name="source_sale_id" value="<?= esc($sourceSale['id']) ?>"><?php endif; ?>

            <?php if (! empty($sourceSale['id'])): ?>
                <div class="col-12">
                    <div class="alert alert-light border rounded-4 mb-0">
                        <strong>Documento origen:</strong>
                        <?= esc(($sourceSale['document_code'] ?? 'DOC') . ' ' . ($sourceSale['sale_number'] ?? '')) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-12">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Fecha y hora</label>
                        <input type="datetime-local" name="issue_date" class="form-control" value="<?= esc(old('issue_date', ! empty($sale['issue_date']) ? date('Y-m-d\TH:i', strtotime($sale['issue_date'])) : date('Y-m-d\TH:i'))) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cliente</label>
                        <select name="customer_id" class="form-select">
                            <!-- <option value="">Consumidor Final</option> -->
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= esc($customer['id']) ?>" <?= old('customer_id', $sale['customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= esc($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Deposito origen</label>
                        <select name="warehouse_id" class="form-select" id="sale-warehouse" required>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= esc($warehouse['id']) ?>" <?= $defaultWarehouseId === $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Comprobante</label>
                        <select name="document_type_id" class="form-select">
                            <?php foreach (($documentTypes ?? []) as $documentType): ?>
                                <option value="<?= esc($documentType['id']) ?>" <?= old('document_type_id', $sale['document_type_id'] ?? (($documentTypes[0]['id'] ?? ''))) === $documentType['id'] ? 'selected' : '' ?>><?= esc($documentType['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Punto de venta</label>
                        <select name="point_of_sale_id" class="form-select">
                            <?php foreach (($pointsOfSale ?? []) as $pointOfSale): ?>
                                <option value="<?= esc($pointOfSale['id']) ?>" <?= old('point_of_sale_id', $sale['point_of_sale_id'] ?? (($pointsOfSale[0]['id'] ?? ''))) === $pointOfSale['id'] ? 'selected' : '' ?>><?= esc($pointOfSale['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lista de precios</label>
                        <select name="price_list_id" class="form-select">
                            <option value="">Precio base</option>
                            <?php foreach (($priceLists ?? []) as $priceList): ?>
                                <option value="<?= esc($priceList['id']) ?>" <?= old('price_list_id', $sale['price_list_id'] ?? '') === $priceList['id'] ? 'selected' : '' ?>><?= esc($priceList['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="price_list_name" value="<?= esc(old('price_list_name', $sale['price_list_name'] ?? 'Lista General')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vendedor</label>
                        <select name="sales_agent_id" class="form-select">
                            <option value="">Sin vendedor</option>
                            <?php foreach (($agents ?? []) as $agent): ?>
                                <option value="<?= esc($agent['id']) ?>" <?= old('sales_agent_id', $sale['sales_agent_id'] ?? ($sale['customer_sales_agent_id'] ?? '')) === $agent['id'] ? 'selected' : '' ?>><?= esc($agent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Zona</label>
                        <select name="sales_zone_id" class="form-select">
                            <option value="">Sin zona</option>
                            <?php foreach (($zones ?? []) as $zone): ?>
                                <option value="<?= esc($zone['id']) ?>" <?= old('sales_zone_id', $sale['sales_zone_id'] ?? '') === $zone['id'] ? 'selected' : '' ?>><?= esc($zone['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Condicion comercial</label>
                        <select name="sales_condition_id" class="form-select">
                            <option value="">Sin condicion</option>
                            <?php foreach (($conditions ?? []) as $condition): ?>
                                <option value="<?= esc($condition['id']) ?>" <?= old('sales_condition_id', $sale['sales_condition_id'] ?? '') === $condition['id'] ? 'selected' : '' ?>><?= esc($condition['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency_code" class="form-select">
                            <?php foreach (($currencyOptions ?? []) as $code => $label): ?>
                                <option value="<?= esc($code) ?>" <?= old('currency_code', $sale['currency_code'] ?? ($salesSettings['default_currency_code'] ?? $currencyCode)) === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descuento global</label>
                        <input type="number" onkeypress="return event.charCode >= 48 && event.charCode <= 57" name="global_discount_total" class="form-control" id="global-discount" value="<?= esc(old('global_discount_total', $sale['global_discount_total'] ?? '0')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vencimiento</label>
                        <input type="datetime-local" name="due_date" class="form-control" value="<?= esc($defaultDueDate) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observacion</label>
                        <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes', $sale['notes'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="border rounded-4 p-3 p-lg-4 h-100 bg-white">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h3 class="h5 mb-1">Detalle de productos</h3>
                                <p class="text-secondary mb-0">Cada linea valida stock disponible en el deposito seleccionado.</p>
                            </div>
                            <button type="button" class="btn btn-dark icon-btn" id="open-sale-search" title="Buscar productos" aria-label="Buscar productos"><i class="bi bi-search"></i></button>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Desc. %</th><th>Impuesto</th><th>Stock</th><th>Total</th><th></th></tr>
                                </thead>
                                <tbody id="sale-items-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="border rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="h5 mb-1">Pagos</h3>
                            <p class="text-secondary mb-0">Puedes registrar uno o varios pagos asociados al borrador.</p>
                        </div>
                        <button type="button" class="btn btn-outline-dark icon-btn" id="add-sale-payment" title="Agregar pago" aria-label="Agregar pago"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Metodo</th><th>Monto</th><th>Referencia</th><th>Fecha</th><th>Nota</th><th></th></tr>
                            </thead>
                            <tbody id="sale-payments-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="row g-3">
                    <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Subtotal</div><div class="fs-4 fw-semibold" id="sale-subtotal">0,00</div></div></div>
                    <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Impuestos</div><div class="fs-4 fw-semibold" id="sale-tax-total">0,00</div></div></div>
                    <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Pagado</div><div class="fs-4 fw-semibold" id="sale-paid-total">0,00</div></div></div>
                    <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Total</div><div class="fs-4 fw-semibold" id="sale-total">0,00</div></div></div>
                </div>
            </div>

            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="saleSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header">
                <div>
                    <h2 class="h5 mb-1">Buscar productos</h2>
                    <p class="text-secondary mb-0">Busca por codigo, nombre o marca y selecciona los productos a agregar.</p>
                </div>
                <button type="button" class="btn btn-outline-dark icon-btn" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label">Producto</label>
                    <input type="text" id="sale-search" class="form-control" placeholder="Escribe para buscar" autocomplete="off">
                </div>
                <div class="small text-secondary mb-3">
                    Coincidencias: <span class="fw-semibold" id="sale-results-count">0</span>
                </div>
                <div id="sale-search-results" class="list-group border rounded-4 overflow-auto" style="max-height: 360px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark icon-btn" data-bs-dismiss="modal" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></button>
                <button type="button" class="btn btn-dark icon-btn" id="accept-sale-search" title="Aceptar" aria-label="Aceptar"><i class="bi bi-check-lg"></i></button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const products = <?= json_encode($productCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const taxes = <?= json_encode($taxCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const existingItems = <?= json_encode(array_values(array_map(static function (array $item): array { return ['product_id' => $item['product_id'], 'quantity' => (float) ($item['quantity'] ?? 0), 'unit_price' => (float) ($item['unit_price'] ?? 0), 'discount_rate' => (float) ($item['discount_rate'] ?? 0), 'tax_id' => $item['tax_id'] ?? '']; }, $saleItems)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const existingPayments = <?= json_encode(array_values(array_map(static function (array $payment): array { return ['payment_method' => $payment['payment_method'] ?? '', 'amount' => (float) ($payment['amount'] ?? 0), 'reference' => $payment['reference'] ?? '', 'paid_at' => ! empty($payment['paid_at']) ? date('Y-m-d\TH:i', strtotime($payment['paid_at'])) : '', 'notes' => $payment['notes'] ?? '']; }, $salePayments)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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
    if (!body || !paymentsBody || !warehouseField || !globalDiscount || !searchField || !resultsContainer || !resultsCount || !openSearchButton || !searchModalElement || !acceptSearchButton) return;
    const resolveSearchModal = () => {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(searchModalElement);
    };
    const productMap = Object.fromEntries(products.map((p) => [p.id, p]));
    const taxMap = Object.fromEntries(taxes.map((t) => [t.id, t]));
    const formatMoney = (value) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
    let itemIndex = 0;
    let paymentIndex = 0;
    const selectedProducts = new Map();

    const taxOptions = (selected = '') => ['<option value="">Sin impuesto</option>', ...taxes.map((t) => `<option value="${t.id}" ${selected === t.id ? 'selected' : ''}>${t.name} (${Number(t.rate).toFixed(2)}%)</option>`)].join('');
    const paymentOptions = (selected = '') => [['cash', 'Efectivo'], ['card', 'Tarjeta'], ['transfer', 'Transferencia'], ['mixed', 'Mixto']].map(([value, label]) => `<option value="${value}" ${selected === value ? 'selected' : ''}>${label}</option>`).join('');

    const availableStock = (productId) => {
        const warehouseId = warehouseField.value;
        const product = productMap[productId];
        const stock = product && product.stocks ? product.stocks[warehouseId] : null;
        return Number(stock && stock.available ? stock.available : 0);
    };

    const syncTotals = () => {
        let subtotal = 0; let taxTotal = 0; let paidTotal = 0;
        body.querySelectorAll('tr').forEach((row) => {
            const qty = Number(row.querySelector('.sale-quantity')?.value || 0);
            const price = Number(row.querySelector('.sale-unit-price')?.value || 0);
            const discount = Number(row.querySelector('.sale-discount-rate')?.value || 0);
            const taxId = row.querySelector('.sale-tax-id')?.value || '';
            const base = Math.max(0, (qty * price) * (1 - (discount / 100)));
            subtotal += base;
            taxTotal += base * (Number((taxMap[taxId] || {}).rate || 0) / 100);
        });
        paymentsBody.querySelectorAll('.sale-payment-amount').forEach((field) => { paidTotal += Number(field.value || 0); });
        const total = Math.max(0, subtotal + taxTotal - Number(globalDiscount.value || 0));
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
            const base = Math.max(0, (qty * price) * (1 - (discount / 100)));
            const lineTotal = base + (base * (Number((taxMap[taxField.value] || {}).rate || 0) / 100));
            totalLabel.textContent = formatMoney(lineTotal);
            qtyField.classList.toggle('is-invalid', Boolean(productId) && qty > available && warehouseField.value !== '');
            syncTotals();
        };
        row.querySelectorAll('select, input').forEach((field) => {
            field.addEventListener('change', sync);
            field.addEventListener('input', sync);
        });
        row.querySelector('.sale-unit-price').addEventListener('input', (event) => { event.target.dataset.touched = '1'; });
        row.querySelector('.remove-sale-item').addEventListener('click', () => { row.remove(); syncTotals(); });
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
                        <div>
                            <div class="fw-semibold">${product.sku} - ${product.name}</div>
                            <div class="small text-secondary">${product.brand || 'Sin marca'}</div>
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
        paymentsBody.appendChild(row);
        row.querySelectorAll('select, input').forEach((field) => { field.addEventListener('change', syncTotals); field.addEventListener('input', syncTotals); });
        row.querySelector('.remove-sale-payment').addEventListener('click', () => { row.remove(); syncTotals(); });
        paymentIndex += 1;
        syncTotals();
    };

    document.getElementById('add-sale-payment').addEventListener('click', () => addPaymentRow());
    warehouseField.addEventListener('change', refreshItemStocks);
    globalDiscount.addEventListener('input', syncTotals);
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
        setTimeout(() => searchField.focus(), 150);
    });
    searchField.addEventListener('input', () => renderResults(matchingProducts(searchField.value)));
    searchField.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        const results = matchingProducts(searchField.value);
        if (results.length > 0) {
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

    existingItems.forEach((item) => addItemRow(item));
    existingPayments.forEach((payment) => addPaymentRow(payment));
    refreshItemStocks();
    syncTotals();
})();
</script>
<?= $this->endSection() ?>
