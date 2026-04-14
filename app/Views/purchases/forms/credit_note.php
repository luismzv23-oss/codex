<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Nota de credito proveedor</h2>
            <p class="text-secondary mb-0">Registrar ajuste financiero sobre una factura proveedor.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4"><label class="form-label">Proveedor</label><select name="supplier_id" class="form-select" required><?php foreach ($suppliers as $supplier): ?><option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Factura origen</label><select name="purchase_invoice_id" class="form-select"><option value="">Sin factura origen</option><?php foreach ($invoices as $invoice): ?><option value="<?= esc($invoice['id']) ?>"><?= esc($invoice['invoice_number'] . ' / ' . $invoice['supplier_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Numero NC</label><input type="text" name="credit_note_number" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Monto</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Fecha</label><input type="datetime-local" name="issue_date" class="form-control" value="<?= esc(date('Y-m-d\TH:i')) ?>"></div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
