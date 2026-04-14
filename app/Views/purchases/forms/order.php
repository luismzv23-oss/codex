<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$productCatalog = array_values(array_map(static function (array $product): array {
    return ['id' => $product['id'], 'sku' => $product['sku'], 'name' => $product['name'], 'cost' => (float) ($product['purchase_price'] ?? 0)];
}, $products ?? []));
?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Orden de compra</h2>
            <p class="text-secondary mb-0">Registrar abastecimiento previsto para Inventario.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3" id="purchase-order-form">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4"><label class="form-label">Fecha</label><input type="datetime-local" name="issued_at" class="form-control" value="<?= esc(old('issued_at', date('Y-m-d\TH:i'))) ?>"></div>
            <div class="col-md-4"><label class="form-label">Proveedor</label><select name="supplier_id" class="form-select" required><option value="">Seleccionar</option><?php foreach ($suppliers as $supplier): ?><option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Deposito destino</label><select name="warehouse_id" class="form-select" required><?php foreach ($warehouses as $warehouse): ?><option value="<?= esc($warehouse['id']) ?>"><?= esc($warehouse['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Moneda</label><select name="currency_code" class="form-select"><?php foreach (($currencyOptions ?? []) as $code => $label): ?><option value="<?= esc($code) ?>"><?= esc($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Entrega esperada</label><input type="datetime-local" name="expected_at" class="form-control" value="<?= esc(old('expected_at', date('Y-m-d\TH:i', strtotime('+72 hours')))) ?>"></div>
            <div class="col-12"><label class="form-label">Observacion</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>
            <div class="col-12">
                <div class="border rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div><h3 class="h5 mb-1">Detalle de compra</h3><p class="text-secondary mb-0">Productos, cantidades y costo previsto.</p></div>
                        <button type="button" class="btn btn-outline-dark icon-btn" id="purchase-add-item"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Producto</th><th>Cant.</th><th>Costo</th><th>IVA %</th><th>Total</th><th></th></tr></thead>
                            <tbody id="purchase-items-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<script>
(() => {
    const products = <?= json_encode($productCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const body = document.getElementById('purchase-items-body');
    const addButton = document.getElementById('purchase-add-item');
    if (!body || !addButton) return;

    const options = () => products.map((product) => `<option value="${product.id}" data-cost="${Number(product.cost || 0).toFixed(2)}">${product.sku} - ${product.name}</option>`).join('');
    const syncRow = (row) => {
        const select = row.querySelector('.purchase-product');
        const quantity = row.querySelector('.purchase-quantity');
        const cost = row.querySelector('.purchase-cost');
        const rate = row.querySelector('.purchase-rate');
        const total = row.querySelector('.purchase-total');
        const selected = select.options[select.selectedIndex];
        if (cost.value === '' || Number(cost.value) <= 0) {
            cost.value = Number(selected?.dataset.cost || 0).toFixed(2);
        }
        const net = Number(quantity.value || 0) * Number(cost.value || 0);
        total.textContent = (net + (net * (Number(rate.value || 0) / 100))).toFixed(2);
    };

    const addRow = () => {
        const row = document.createElement('tr');
        row.innerHTML = `<td><select name="items_product_id[]" class="form-select purchase-product">${options()}</select><input type="hidden" name="items_description[]" value=""></td><td><input type="number" step="0.01" min="0.01" name="items_quantity[]" class="form-control purchase-quantity" value="1.00"></td><td><input type="number" step="0.01" min="0" name="items_unit_cost[]" class="form-control purchase-cost" value="0.00"></td><td><input type="number" step="0.01" min="0" name="items_tax_rate[]" class="form-control purchase-rate" value="21.00"></td><td class="purchase-total">0.00</td><td class="text-end"><button type="button" class="btn btn-outline-dark icon-btn purchase-remove"><i class="bi bi-x-lg"></i></button></td>`;
        body.appendChild(row);
        row.querySelectorAll('select,input').forEach((field) => field.addEventListener('input', () => syncRow(row)));
        row.querySelector('.purchase-remove').addEventListener('click', () => row.remove());
        syncRow(row);
    };

    addButton.addEventListener('click', addRow);
    addRow();
})();
</script>
<?= $this->endSection() ?>
