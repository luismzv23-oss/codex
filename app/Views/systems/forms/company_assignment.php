<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Asignar sistema a empresa</h2>
            <p class="text-secondary mb-0">Habilita sistemas funcionales para una empresa y define su estado inicial.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Empresa</label>
                <select class="form-select" name="company_id" required>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= old('company_id', $selectedCompanyId ?? '') === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sistema</label>
                <select class="form-select" name="system_id" required>
                    <?php foreach ($systems as $system): ?>
                        <option value="<?= esc($system['id']) ?>" <?= old('system_id') === $system['id'] ? 'selected' : '' ?>><?= esc($system['name']) ?> (<?= esc($system['slug']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="active"><option value="1" <?= old('active', '1') === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= old('active') === '0' ? 'selected' : '' ?>>Inactivo</option></select></div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('sistemas?company_id=' . ($selectedCompanyId ?? ''))) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
