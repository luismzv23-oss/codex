<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1"><?= esc($pageTitle) ?></h1>
        <p class="text-secondary mb-4">Configurar parámetros, tipo y cuentas asociadas para la caja registradora.</p>
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>

            <div class="col-md-6">
                <label class="form-label">Nombre de la caja</label>
                <input type="text" name="name" class="form-control" value="<?= esc($register['name'] ?? '') ?>" placeholder="Ej: Caja Principal, Caja POS 1" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Código único</label>
                <input type="text" name="code" class="form-control" value="<?= esc($register['code'] ?? '') ?>" placeholder="Ej: CAJA-01, POS-01" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Tipo de caja</label>
                <select name="register_type" class="form-select" required>
                    <option value="general" <?= ($register['register_type'] ?? '') === 'general' ? 'selected' : '' ?>>General</option>
                    <option value="pos" <?= ($register['register_type'] ?? '') === 'pos' ? 'selected' : '' ?>>Punto de Venta (POS)</option>
                    <option value="kiosk" <?= ($register['register_type'] ?? '') === 'kiosk' ? 'selected' : '' ?>>Kiosco</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Sucursal</label>
                <select name="branch_id" class="form-select" required>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= esc($branch['id']) ?>" <?= ($register['branch_id'] ?? '') === $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Cuenta contable (Opcional)</label>
                <select name="account_id" class="form-select">
                    <option value="">-- Usar cuenta global de empresa --</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= esc($account['id']) ?>" <?= ($register['account_id'] ?? '') === $account['id'] ? 'selected' : '' ?>><?= esc($account['name']) ?> (<?= esc($account['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text small text-secondary">Si se selecciona, las diferencias de arqueo al cierre impactarán en esta cuenta.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Punto de venta asociado (Opcional)</label>
                <select name="sales_point_of_sale_id" class="form-select">
                    <option value="">-- Ninguno / Seleccionar punto de venta --</option>
                    <?php foreach ($pointsOfSale as $pos): ?>
                        <option value="<?= esc($pos['id']) ?>" <?= ($register['sales_point_of_sale_id'] ?? '') === $pos['id'] ? 'selected' : '' ?>><?= esc($pos['name']) ?> (<?= esc($pos['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text small text-secondary">Vincular esta caja registradora a un punto de venta o terminal física.</div>
            </div>

            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefaultCheck" <?= (!empty($register['is_default'])) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isDefaultCheck">
                        Establecer como caja por defecto
                    </label>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select name="active" class="form-select" required>
                    <option value="1" <?= ($register['active'] ?? 1) === 1 ? 'selected' : '' ?>>Activa</option>
                    <option value="0" <?= ($register['active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactiva</option>
                </select>
            </div>

            <div class="col-12 d-flex gap-2 mt-4">
                <button class="btn btn-dark icon-btn" title="Guardar caja" aria-label="Guardar caja"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
