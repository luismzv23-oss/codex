<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1"><?= empty($warehouse) ? 'Deposito nuevo' : 'Editar deposito' ?></h2>
            <p class="text-secondary mb-0">Define la ubicacion operativa, la sucursal asociada y su papel logistico.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-8">
                <label class="form-label">Deposito</label>
                <input type="text" name="name" class="form-control" value="<?= esc(old('name', $warehouse['name'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Codigo</label>
                <input type="text" name="code" class="form-control text-uppercase" value="<?= esc(old('code', $warehouse['code'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sucursal</label>
                <select name="branch_id" class="form-select">
                    <option value="">Sin sucursal</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= esc($branch['id']) ?>" <?= old('branch_id', $warehouse['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tipo</label>
                <select name="type" class="form-select">
                    <?php foreach (['central' => 'Central', 'general' => 'General', 'taller' => 'Taller', 'movil' => 'Unidad movil'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= old('type', $warehouse['type'] ?? 'general') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Descripcion</label>
                <textarea name="description" class="form-control" rows="3"><?= esc(old('description', $warehouse['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Deposito base</label>
                <select name="is_default" class="form-select">
                    <option value="0" <?= (string) old('is_default', (string) ($warehouse['is_default'] ?? '0')) === '0' ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= (string) old('is_default', (string) ($warehouse['is_default'] ?? '0')) === '1' ? 'selected' : '' ?>>Si</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select name="active" class="form-select">
                    <option value="1" <?= (string) old('active', (string) ($warehouse['active'] ?? '1')) === '1' ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= (string) old('active', (string) ($warehouse['active'] ?? '1')) === '0' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
