<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-3"><h2 class="h5 mb-0">Nueva Cuenta Contable</h2></div>
<form method="post" action="<?= esc($formAction) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
    <?php if ($isPopup ?? false): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Codigo</label><input type="text" name="code" class="form-control" required placeholder="1.1.01" value="<?= old('code') ?>"></div>
        <div class="col-md-5"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" required placeholder="Caja" value="<?= old('name') ?>"></div>
        <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="account_type" class="form-select">
                <option value="asset">Activo</option>
                <option value="liability">Pasivo</option>
                <option value="equity">Patrimonio Neto</option>
                <option value="revenue">Ingreso</option>
                <option value="expense">Egreso</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Cuenta padre</label>
            <select name="parent_id" class="form-select">
                <option value="">(Raiz)</option>
                <?php foreach ($parents ?? [] as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['code']) ?> — <?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Es grupo</label>
            <select name="is_group" class="form-select">
                <option value="0">No (imputable)</option>
                <option value="1">Si (agrupador)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Acepta asientos</label>
            <select name="accepts_entries" class="form-select">
                <option value="1">Si</option>
                <option value="0">No</option>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Moneda</label><input type="text" name="currency_code" class="form-control" value="ARS"></div>
        <div class="col-md-2"><label class="form-label">Saldo apertura</label><input type="number" step="0.01" name="opening_balance" class="form-control" value="0"></div>
        <div class="col-12 text-end"><button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button></div>
    </div>
</form>
<?= $this->endSection() ?>
