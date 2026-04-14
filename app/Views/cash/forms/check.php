<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Cheque</h2>
        <p class="text-secondary mb-0">Registrar cheque recibido o emitido para tesoreria.</p>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3 mt-1">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4"><label class="form-label">Tipo</label><select name="check_type" class="form-select"><option value="received">Recibido</option><option value="issued">Emitido</option></select></div>
            <div class="col-md-4"><label class="form-label">Numero</label><input type="text" name="check_number" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Banco</label><input type="text" name="bank_name" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Emisor</label><input type="text" name="issuer_name" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Vencimiento</label><input type="date" name="due_date" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Monto</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Cliente</label><select name="customer_id" class="form-select"><option value="">Sin cliente</option><?php foreach ($customers as $customer): ?><option value="<?= esc($customer['id']) ?>"><?= esc($customer['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Proveedor</label><select name="supplier_id" class="form-select"><option value="">Sin proveedor</option><?php foreach ($suppliers as $supplier): ?><option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Estado</label><select name="status" class="form-select"><option value="portfolio">En cartera</option><option value="received">Recibido</option><option value="issued">Emitido</option><option value="cleared">Acreditado</option></select></div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
