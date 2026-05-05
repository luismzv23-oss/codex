<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2 class="h5 mb-0"><?= !empty($fromQuote) ? 'Pedido desde Presupuesto #' . esc($fromQuote['quote_number'] ?? '') : 'Nuevo Pedido' ?></h2><p class="text-secondary mb-0 small">Registra el pedido del cliente para gestionar entregas y facturacion.</p></div>
</div>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3" id="order-form">
            <?= csrf_field() ?>
            <?php if (!empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <?php if (!empty($fromQuote['id'])): ?><input type="hidden" name="sales_quote_id" value="<?= esc($fromQuote['id']) ?>"><?php endif; ?>
            <div class="col-md-3"><label class="form-label">Fecha pedido</label><input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Entrega esperada</label><input type="date" name="expected_delivery_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select name="customer_id" class="form-select">
                    <option value="">Consumidor Final</option>
                    <?php foreach ($customers ?? [] as $c): ?>
                        <option value="<?= esc($c['id']) ?>" <?= ($fromQuote['customer_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Vendedor</label>
                <select name="sales_agent_id" class="form-select">
                    <option value="">Sin vendedor</option>
                    <?php foreach ($agents ?? [] as $a): ?>
                        <option value="<?= esc($a['id']) ?>" <?= ($fromQuote['sales_agent_id'] ?? '') === $a['id'] ? 'selected' : '' ?>><?= esc($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Condicion</label>
                <select name="sales_condition_id" class="form-select">
                    <option value="">Sin condicion</option>
                    <?php foreach ($conditions ?? [] as $cond): ?>
                        <option value="<?= esc($cond['id']) ?>" <?= ($fromQuote['sales_condition_id'] ?? '') === $cond['id'] ? 'selected' : '' ?>><?= esc($cond['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Moneda</label><select name="currency_code" class="form-select"><?php foreach ($currencyOptions ?? ['ARS' => 'ARS'] as $code => $label): ?><option value="<?= esc($code) ?>" <?= ($fromQuote['currency_code'] ?? 'ARS') === $code ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></div>

            <div class="col-12">
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Lineas del pedido</h3>
                        <button type="button" class="btn btn-dark btn-sm" onclick="addOrderLine()"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Unit.</th><th>Desc. %</th><th>IVA %</th><th>Total</th><th></th></tr></thead>
                        <tbody id="order-items"></tbody>
                        <tfoot class="table-dark"><tr><td colspan="5" class="fw-bold">Total</td><td class="fw-bold" id="order-total">0,00</td><td></td></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-6"><label class="form-label">Observaciones</label><textarea name="notes" class="form-control" rows="2"><?= esc($fromQuote['notes'] ?? '') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Notas internas</label><textarea name="internal_notes" class="form-control" rows="2"><?= esc($fromQuote['internal_notes'] ?? '') ?></textarea></div>
            <div class="col-12 text-end"><button class="btn btn-dark"><i class="bi bi-save"></i> Guardar Pedido</button></div>
        </form>
    </div>
</div>
<script>
let oIdx = 0;
const productList = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'sale_price' => (float)($p['sale_price'] ?? 0)], $products ?? []), JSON_UNESCAPED_UNICODE) ?>;
const existingItems = <?= json_encode($fromQuoteItems ?? [], JSON_UNESCAPED_UNICODE) ?>;
const prodOpts = productList.map(p => `<option value="${p.id}" data-price="${p.sale_price}">${p.sku} - ${p.name}</option>`).join('');
function addOrderLine(data = {}) {
    const row = document.createElement('tr');
    const prodId = data.product_id || (productList[0]?.id || '');
    const price = data.unit_price || productList.find(p => p.id === prodId)?.sale_price || 0;
    row.innerHTML = `<td><select name="items[${oIdx}][product_id]" class="form-select form-select-sm" onchange="autoOrdPrice(this)">${prodOpts}</select></td>
        <td><input type="number" step="0.01" min="0.01" name="items[${oIdx}][quantity]" class="form-control form-control-sm" value="${data.quantity||1}" oninput="calcOrd()"></td>
        <td><input type="number" step="0.01" name="items[${oIdx}][unit_price]" class="form-control form-control-sm o-price" value="${Number(price).toFixed(2)}" oninput="calcOrd()"></td>
        <td><input type="number" step="0.01" name="items[${oIdx}][discount_pct]" class="form-control form-control-sm" value="${data.discount_pct||0}" oninput="calcOrd()"></td>
        <td><input type="number" step="0.01" name="items[${oIdx}][tax_rate]" class="form-control form-control-sm" value="${data.tax_rate||21}" oninput="calcOrd()"></td>
        <td class="fw-semibold o-line-total">0,00</td>
        <td><button type="button" class="btn btn-outline-danger btn-sm icon-btn" onclick="this.closest('tr').remove();calcOrd()"><i class="bi bi-trash"></i></button></td>`;
    if (data.product_id) row.querySelector('select').value = data.product_id;
    document.getElementById('order-items').appendChild(row);
    oIdx++;
    calcOrd();
}
function autoOrdPrice(sel) {
    const p = sel.selectedOptions[0]?.dataset.price || 0;
    sel.closest('tr').querySelector('.o-price').value = Number(p).toFixed(2);
    calcOrd();
}
function calcOrd() {
    let total = 0;
    document.querySelectorAll('#order-items tr').forEach(row => {
        const q = parseFloat(row.querySelector('[name*=quantity]')?.value||0);
        const p = parseFloat(row.querySelector('[name*=unit_price]')?.value||0);
        const d = parseFloat(row.querySelector('[name*=discount_pct]')?.value||0);
        const t = parseFloat(row.querySelector('[name*=tax_rate]')?.value||0);
        const base = q * p * (1 - d / 100);
        const lt = base + base * (t / 100);
        row.querySelector('.o-line-total').textContent = lt.toLocaleString('es-AR', {minimumFractionDigits:2});
        total += lt;
    });
    document.getElementById('order-total').textContent = total.toLocaleString('es-AR', {minimumFractionDigits:2});
}
if (existingItems.length > 0) {
    existingItems.forEach(i => addOrderLine(i));
} else {
    addOrderLine();
}
</script>
<?= $this->endSection() ?>
