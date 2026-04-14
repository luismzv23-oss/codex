<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Editar empresa</h2>
            <p class="text-secondary mb-0">Actualiza los datos maestros de la empresa dentro de su configuracion central.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <input type="hidden" name="company_id" value="<?= esc($company['id'] ?? '') ?>">
            <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="name" value="<?= esc($company['name'] ?? '') ?>" required></div>
            <div class="col-md-6"><label class="form-label">Razon social</label><input class="form-control" name="legal_name" value="<?= esc($company['legal_name'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Cuit</label><input class="form-control" name="tax_id" value="<?= esc($company['tax_id'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Correo</label><input class="form-control" name="email" value="<?= esc($company['email'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Telefono</label><input class="form-control" name="phone" value="<?= esc($company['phone'] ?? '') ?>"></div>
            <div class="col-md-6">
                <label class="form-label">Moneda base</label>
                <select class="form-select" name="currency_code" required>
                    <?php foreach ($currencyOptions as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($company['currency_code'] ?? 'ARS') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="form-label">Direccion</label><textarea class="form-control" name="address" rows="3"><?= esc($company['address'] ?? '') ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('configuracion?company_id=' . ($company['id'] ?? ''))) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
