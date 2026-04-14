<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Recepcion de compra</h2>
            <p class="text-secondary mb-0">Registrar ingreso real de mercaderia e impacto en stock.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <input type="hidden" name="purchase_order_id" value="<?= esc($order['id']) ?>">
            <div class="col-md-4"><label class="form-label">Orden</label><input type="text" class="form-control" value="<?= esc($order['order_number']) ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Proveedor</label><input type="text" class="form-control" value="<?= esc($supplier['name'] ?? '-') ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Deposito</label><input type="text" class="form-control" value="<?= esc($warehouse['name'] ?? '-') ?>" readonly></div>
            <div class="col-md-6"><label class="form-label">Documento proveedor</label><input type="text" name="supplier_document" class="form-control" value="<?= esc(old('supplier_document')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Fecha</label><input type="datetime-local" name="issued_at" class="form-control" value="<?= esc(old('issued_at', date('Y-m-d\TH:i'))) ?>"></div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Producto</th><th>Pendiente</th><th>Recibir</th><th>Costo</th><th>IVA %</th><th>Lote/Serie</th><th>Vence</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): $pending = max(0, (float) $item['quantity'] - (float) $item['received_quantity']); ?>
                            <tr>
                                <td><?= esc($item['sku'] . ' - ' . $item['product_name']) ?><input type="hidden" name="items_order_item_id[]" value="<?= esc($item['id']) ?>"></td>
                                <td><?= number_format($pending, 2, ',', '.') ?></td>
                                <td><input type="number" step="0.01" min="0" max="<?= esc((string) $pending) ?>" name="items_quantity[]" class="form-control" value="<?= esc(number_format($pending, 2, '.', '')) ?>"></td>
                                <td><input type="number" step="0.01" min="0" name="items_unit_cost[]" class="form-control" value="<?= esc(number_format((float) $item['unit_cost'], 2, '.', '')) ?>"></td>
                                <td><input type="number" step="0.01" min="0" name="items_tax_rate[]" class="form-control" value="<?= esc(number_format((float) $item['tax_rate'], 2, '.', '')) ?>"></td>
                                <td><div class="d-flex gap-2"><input type="text" name="items_lot_number[]" class="form-control" placeholder="Lote"><input type="text" name="items_serial_number[]" class="form-control" placeholder="Serie"></div></td>
                                <td><input type="date" name="items_expiration_date[]" class="form-control"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
