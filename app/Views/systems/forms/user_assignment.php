<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Asignar sistema a usuario</h2>
            <p class="text-secondary mb-0">Define el acceso funcional dentro de los sistemas ya habilitados para la empresa.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <input type="hidden" name="company_id" value="<?= esc($selectedCompanyId ?? '') ?>">
            <div class="col-12">
                <label class="form-label">Empresa</label>
                <input class="form-control" value="<?= esc($selectedCompany['name'] ?? '') ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= esc($userLabel ?? 'Usuario') ?></label>
                <select class="form-select" name="user_id" required>
                    <?php foreach ($users as $userRow): ?>
                        <option value="<?= esc($userRow['id']) ?>" <?= old('user_id') === $userRow['id'] ? 'selected' : '' ?>><?= esc($userRow['name']) ?> (<?= esc($userRow['username']) ?>)</option>
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
            <div class="col-md-6"><label class="form-label">Nivel de acceso</label><select class="form-select" name="access_level"><option value="view" <?= old('access_level', 'view') === 'view' ? 'selected' : '' ?>>Consulta</option><option value="manage" <?= old('access_level') === 'manage' ? 'selected' : '' ?>>Gestion</option></select></div>
            <div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="active"><option value="1" <?= old('active', '1') === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= old('active') === '0' ? 'selected' : '' ?>>Inactivo</option></select></div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('sistemas?company_id=' . ($selectedCompanyId ?? ''))) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
