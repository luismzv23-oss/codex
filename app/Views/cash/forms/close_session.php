<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Cierre de caja</h1>
        <p class="text-secondary mb-4">Completa el arqueo real y registra la diferencia contra el saldo esperado.</p>
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Apertura</label>
                <input type="text" class="form-control" value="<?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Saldo esperado</label>
                <input type="text" class="form-control" value="<?= number_format((float) $expectedAmount, 2, ',', '.') ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Saldo real</label>
                <input type="number" step="0.01" name="actual_closing_amount" class="form-control" value="<?= number_format((float) $expectedAmount, 2, '.', '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-control" value="">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark icon-btn" title="Cerrar caja" aria-label="Cerrar caja"><i class="bi bi-check-lg"></i></button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
