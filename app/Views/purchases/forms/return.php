<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Devolucion a proveedor</h2>
            <p class="text-secondary mb-0">Registrar egreso al proveedor y ajuste de deuda asociada.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <input type="hidden" name="purchase_receipt_id" value="<?= esc($receipt['id']) ?>">
            <div class="col-md-6"><label class="form-label">Recepcion</label><input type="text" class="form-control" value="<?= esc($receipt['receipt_number']) ?>" readonly></div>
            <div class="col-md-6"><label class="form-label">Proveedor</label><input type="text" class="form-control" value="<?= esc($supplier['name'] ?? '-') ?>" readonly></div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Producto</th><th>Recibido</th><th>Devolver</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= esc($item['sku'] . ' - ' . $item['product_name']) ?><input type="hidden" name="items_receipt_item_id[]" value="<?= esc($item['id']) ?>"></td>
                                <td><?= number_format((float) $item['quantity'], 2, ',', '.') ?></td>
                                <td><input type="number" step="0.01" min="0" max="<?= esc(number_format((float) $item['quantity'], 2, '.', '')) ?>" name="items_quantity[]" class="form-control" value="0.00"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6"><label class="form-label">Motivo</label><input type="text" name="reason" class="form-control" value="<?= esc(old('reason')) ?>"></div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
