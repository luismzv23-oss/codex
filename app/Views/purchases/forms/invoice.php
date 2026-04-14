<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Factura proveedor</h2>
            <p class="text-secondary mb-0">Registrar factura asociada o independiente de una recepcion.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4"><label class="form-label">Proveedor</label><select name="supplier_id" class="form-select" required><?php foreach ($suppliers as $supplier): ?><option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Recepcion</label><select name="purchase_receipt_id" class="form-select"><option value="">Sin recepcion</option><?php foreach ($receipts as $receipt): ?><option value="<?= esc($receipt['id']) ?>"><?= esc($receipt['receipt_number'] . ' / ' . $receipt['supplier_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Factura</label><input type="text" name="invoice_number" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Moneda</label><select name="currency_code" class="form-select"><?php foreach ($currencyOptions as $code => $label): ?><option value="<?= esc((string) $code) ?>"><?= esc((string) $label) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Cotizacion</label><input type="number" step="0.000001" min="0.000001" name="exchange_rate" class="form-control" value="1"></div>
            <div class="col-md-3"><label class="form-label">Fecha</label><input type="datetime-local" name="issue_date" class="form-control" value="<?= esc(date('Y-m-d\TH:i')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Vencimiento</label><input type="datetime-local" name="due_date" class="form-control"></div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Descripcion</th><th>Cant.</th><th>Costo</th><th>IVA %</th></tr></thead>
                        <tbody>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <tr>
                                <td><select name="items_product_id[]" class="form-select"><option value="">Sin producto</option><?php foreach ($products as $product): ?><option value="<?= esc($product['id']) ?>"><?= esc($product['sku'] . ' - ' . $product['name']) ?></option><?php endforeach; ?></select></td>
                                <td><input type="text" name="items_description[]" class="form-control"></td>
                                <td><input type="number" step="0.01" min="0" name="items_quantity[]" class="form-control" value="0"></td>
                                <td><input type="number" step="0.0001" min="0" name="items_unit_cost[]" class="form-control" value="0"></td>
                                <td><input type="number" step="0.01" min="0" name="items_tax_rate[]" class="form-control" value="0"></td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
