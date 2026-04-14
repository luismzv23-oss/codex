<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Proveedor nuevo</h2>
            <p class="text-secondary mb-0">Registrar proveedor operativo para compras y cuentas a pagar.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Proveedor</label><input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Razon social</label><input type="text" name="legal_name" class="form-control" value="<?= esc(old('legal_name')) ?>"></div>
            <div class="col-md-4"><label class="form-label">CUIT / Doc.</label><input type="text" name="tax_id" class="form-control" value="<?= esc(old('tax_id')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Telefono</label><input type="text" name="phone" class="form-control" value="<?= esc(old('phone')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Condicion IVA</label><input type="text" name="vat_condition" class="form-control" value="<?= esc(old('vat_condition')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Plazo dias</label><input type="number" min="0" name="payment_terms_days" class="form-control" value="<?= esc(old('payment_terms_days', '0')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Estado</label><select name="active" class="form-select"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
            <div class="col-12"><label class="form-label">Direccion</label><textarea name="address" class="form-control" rows="3"><?= esc(old('address')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
