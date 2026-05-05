<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2 class="h5 mb-0">Nuevo Presupuesto</h2><p class="text-secondary mb-0 small">Crea un presupuesto y luego convertilo en pedido o factura.</p></div>
</div>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3" id="quote-form">
            <?= csrf_field() ?>
            <?php if (!empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-3"><label class="form-label">Fecha</label><input type="date" name="quote_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Valido hasta</label><input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select name="customer_id" class="form-select">
                    <option value="">Consumidor Final</option>
                    <?php foreach ($customers ?? [] as $c): ?>
                        <option value="<?= esc($c['id']) ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Lista de precios</label>
                <select name="price_list_id" class="form-select">
                    <option value="">Precio base</option>
                    <?php foreach ($priceLists ?? [] as $pl): ?>
                        <option value="<?= esc($pl['id']) ?>"><?= esc($pl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Vendedor</label>
                <select name="sales_agent_id" class="form-select">
                    <option value="">Sin vendedor</option>
                    <?php foreach ($agents ?? [] as $a): ?>
                        <option value="<?= esc($a['id']) ?>"><?= esc($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Zona</label>
                <select name="sales_zone_id" class="form-select">
                    <option value="">Sin zona</option>
                    <?php foreach ($zones ?? [] as $z): ?>
                        <option value="<?= esc($z['id']) ?>"><?= esc($z['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Condicion comercial</label>
                <select name="sales_condition_id" class="form-select">
                    <option value="">Sin condicion</option>
                    <?php foreach ($conditions ?? [] as $cond): ?>
                        <option value="<?= esc($cond['id']) ?>"><?= esc($cond['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Moneda</label>
                <select name="currency_code" class="form-select">
                    <?php foreach ($currencyOptions ?? ['ARS' => 'ARS'] as $code => $label): ?>
                        <option value="<?= esc($code) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Items -->
            <div class="col-12">
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Lineas del presupuesto</h3>
                        <button type="button" class="btn btn-dark btn-sm" onclick="addQuoteLine()"><i class="bi bi-plus-lg"></i> Agregar producto</button>
                    </div>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Unit.</th><th>Desc. %</th><th>IVA %</th><th>Total linea</th><th></th></tr></thead>
                        <tbody id="quote-items"></tbody>
                        <tfoot class="table-dark"><tr><td colspan="5" class="fw-bold">Total</td><td class="fw-bold" id="quote-total">0,00</td><td></td></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">Notas internas</label><textarea name="internal_notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 text-end"><button class="btn btn-dark"><i class="bi bi-save"></i> Guardar Presupuesto</button></div>
        </form>
    </div>
</div>
<script>
let qIdx = 0;
const productList = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'sale_price' => (float)($p['sale_price'] ?? 0)], $products ?? []), JSON_UNESCAPED_UNICODE) ?>;
const prodOpts = productList.map(p => `<option value="${p.id}" data-price="${p.sale_price}">${p.sku} - ${p.name}</option>`).join('');
function addQuoteLine() {
    const row = document.createElement('tr');
    row.innerHTML = `<td><select name="items[${qIdx}][product_id]" class="form-select form-select-sm" onchange="autoPrice(this)">${prodOpts}</select></td>
        <td><input type="number" step="0.01" min="0.01" name="items[${qIdx}][quantity]" class="form-control form-control-sm" value="1" oninput="calcQuote()"></td>
        <td><input type="number" step="0.01" name="items[${qIdx}][unit_price]" class="form-control form-control-sm q-price" value="${productList[0]?.sale_price||0}" oninput="calcQuote()"></td>
        <td><input type="number" step="0.01" name="items[${qIdx}][discount_pct]" class="form-control form-control-sm" value="0" oninput="calcQuote()"></td>
        <td><input type="number" step="0.01" name="items[${qIdx}][tax_rate]" class="form-control form-control-sm" value="21" oninput="calcQuote()"></td>
        <td class="fw-semibold q-line-total">0,00</td>
        <td><button type="button" class="btn btn-outline-danger btn-sm icon-btn" onclick="this.closest('tr').remove();calcQuote()"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('quote-items').appendChild(row);
    qIdx++;
    calcQuote();
}
function autoPrice(sel) {
    const opt = sel.selectedOptions[0];
    const price = opt?.dataset.price || 0;
    sel.closest('tr').querySelector('.q-price').value = Number(price).toFixed(2);
    calcQuote();
}
function calcQuote() {
    let total = 0;
    document.querySelectorAll('#quote-items tr').forEach(row => {
        const q = parseFloat(row.querySelector('[name*=quantity]')?.value || 0);
        const p = parseFloat(row.querySelector('[name*=unit_price]')?.value || 0);
        const d = parseFloat(row.querySelector('[name*=discount_pct]')?.value || 0);
        const t = parseFloat(row.querySelector('[name*=tax_rate]')?.value || 0);
        const base = q * p * (1 - d / 100);
        const lineTotal = base + base * (t / 100);
        row.querySelector('.q-line-total').textContent = lineTotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
        total += lineTotal;
    });
    document.getElementById('quote-total').textContent = total.toLocaleString('es-AR', {minimumFractionDigits: 2});
}
addQuoteLine();
</script>
<?= $this->endSection() ?>
