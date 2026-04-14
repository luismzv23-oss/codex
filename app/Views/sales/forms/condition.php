<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Condicion comercial</h2>
            <p class="text-secondary mb-0">Definir plazos, credito y reglas comerciales para clientes.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Codigo</label><input type="text" name="code" class="form-control" value="<?= esc(old('code')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Limite de credito</label><input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="<?= esc(old('credit_limit', '0')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Plazo dias</label><input type="number" min="0" name="payment_terms_days" class="form-control" value="<?= esc(old('payment_terms_days', '0')) ?>"></div>
            <div class="col-md-4"><label class="form-label">Descuento %</label><input type="number" step="0.01" min="0" name="discount_rate" class="form-control" value="<?= esc(old('discount_rate', '0')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Requiere autorizacion</label><select name="requires_authorization" class="form-select"><option value="0">No</option><option value="1">Si</option></select></div>
            <div class="col-md-6"><label class="form-label">Estado</label><select name="active" class="form-select"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
