<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Zona comercial</h2>
            <p class="text-secondary mb-0">Registrar una zona para clasificar clientes y ventas.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" value="<?= esc(old('name')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Codigo</label><input type="text" name="code" class="form-control" value="<?= esc(old('code')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Region</label><input type="text" name="region" class="form-control" value="<?= esc(old('region')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Estado</label><select name="active" class="form-select"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
            <div class="col-12"><label class="form-label">Descripcion</label><textarea name="description" class="form-control" rows="3"><?= esc(old('description')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
