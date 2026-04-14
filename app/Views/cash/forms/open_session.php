<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Apertura de caja</h1>
        <p class="text-secondary mb-4">Inicia una nueva sesion operativa para ventas, cobros y movimientos del dia.</p>
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-8">
                <label class="form-label">Caja</label>
                <select name="cash_register_id" class="form-select" required>
                    <?php foreach ($registers as $register): ?>
                        <option value="<?= esc($register['id']) ?>"><?= esc($register['name']) ?> (<?= esc($register['register_type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Monto inicial</label>
                <input type="number" step="0.01" min="0" name="opening_amount" class="form-control" value="0.00" required>
            </div>
            <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark icon-btn" title="Abrir caja" aria-label="Abrir caja"><i class="bi bi-check-lg"></i></button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
