<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2 class="h5 mb-0"><?= !empty($fromOrder) ? 'Remito desde Pedido #' . esc($fromOrder['order_number'] ?? '') : 'Nuevo Remito' ?></h2><p class="text-secondary mb-0 small">Comprobante de entrega de mercaderia al cliente.</p></div>
</div>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if (!empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <?php if (!empty($fromOrder['id'])): ?><input type="hidden" name="sales_order_id" value="<?= esc($fromOrder['id']) ?>"><?php endif; ?>
            <div class="col-md-3"><label class="form-label">Fecha remito</label><input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select name="customer_id" class="form-select">
                    <option value="">Consumidor Final</option>
                    <?php foreach ($customers ?? [] as $c): ?>
                        <option value="<?= esc($c['id']) ?>" <?= ($fromOrder['customer_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Deposito</label>
                <select name="warehouse_id" class="form-select" required>
                    <?php foreach ($warehouses ?? [] as $w): ?>
                        <option value="<?= esc($w['id']) ?>"><?= esc($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Transportista</label><input type="text" name="carrier" class="form-control" placeholder="Opcional"></div>
            <div class="col-md-6"><label class="form-label">Direccion de entrega</label><textarea name="shipping_address" class="form-control" rows="2"><?= esc($fromOrder['shipping_address'] ?? '') ?></textarea></div>
            <div class="col-md-3"><label class="form-label">Nro. seguimiento</label><input type="text" name="tracking_number" class="form-control" placeholder="Opcional"></div>

            <div class="col-12">
                <div class="border rounded-4 p-3 bg-light-subtle">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Items a entregar</h3>
                        <button type="button" class="btn btn-dark btn-sm" onclick="addDnLine()"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Cantidad pedida</th><th>Cantidad a entregar</th><th>Lote</th><th>Serie</th><th></th></tr></thead>
                        <tbody id="dn-items"></tbody>
                    </table>
                </div>
            </div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 text-end"><button class="btn btn-dark"><i class="bi bi-save"></i> Guardar Remito</button></div>
        </form>
    </div>
</div>
<script>
let dnIdx = 0;
const productList = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name']], $products ?? []), JSON_UNESCAPED_UNICODE) ?>;
const orderItems = <?= json_encode($fromOrderItems ?? [], JSON_UNESCAPED_UNICODE) ?>;
const prodOpts = productList.map(p => `<option value="${p.id}">${p.sku} - ${p.name}</option>`).join('');
function addDnLine(data = {}) {
    const row = document.createElement('tr');
    const qtyPending = Math.max(0, (data.quantity || 1) - (data.quantity_delivered || 0));
    row.innerHTML = `<td><select name="items[${dnIdx}][product_id]" class="form-select form-select-sm">${prodOpts}</select>
        ${data.sales_order_item_id ? `<input type="hidden" name="items[${dnIdx}][sales_order_item_id]" value="${data.sales_order_item_id}">` : ''}</td>
        <td class="text-secondary">${data.quantity || '-'}</td>
        <td><input type="number" step="0.01" min="0.01" name="items[${dnIdx}][quantity]" class="form-control form-control-sm" value="${qtyPending}" required></td>
        <td><input type="text" name="items[${dnIdx}][lot_number]" class="form-control form-control-sm" placeholder="Opcional"></td>
        <td><input type="text" name="items[${dnIdx}][serial_number]" class="form-control form-control-sm" placeholder="Opcional"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm icon-btn" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>`;
    if (data.product_id) row.querySelector('select').value = data.product_id;
    document.getElementById('dn-items').appendChild(row);
    dnIdx++;
}
if (orderItems.length > 0) {
    orderItems.forEach(i => addDnLine({...i, sales_order_item_id: i.id}));
} else {
    addDnLine();
}
</script>
<?= $this->endSection() ?>
