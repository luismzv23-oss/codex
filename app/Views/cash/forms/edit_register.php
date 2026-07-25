<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Editar caja</h1>
        <p class="text-secondary mb-4">Modifica los datos de la caja configurada.</p>
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Nombre de la caja</label>
                <input type="text" name="name" class="form-control" value="<?= esc($register['name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Código identificador</label>
                <input type="text" name="code" class="form-control" value="<?= esc($register['code']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tipo de caja</label>
                <select name="register_type" class="form-select" required>
                    <option value="general" <?= ($register['register_type'] ?? '') === 'general' ? 'selected' : '' ?>>General (Administración / Fondos generales)</option>
                    <option value="pos" <?= ($register['register_type'] ?? '') === 'pos' ? 'selected' : '' ?>>Venta POS / Facturación rápida</option>
                    <option value="kiosk" <?= ($register['register_type'] ?? '') === 'kiosk' ? 'selected' : '' ?>>Kiosco / Autogestión</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select name="active" class="form-select" required>
                    <option value="1" <?= (int) ($register['active'] ?? 0) === 1 ? 'selected' : '' ?>>Activa</option>
                    <option value="0" <?= (int) ($register['active'] ?? 0) === 0 ? 'selected' : '' ?>>Inactiva</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark icon-btn" title="Guardar cambios" aria-label="Guardar cambios"><i class="bi bi-check-lg"></i></button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
