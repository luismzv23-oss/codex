<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Cierre de inventario</h2>
            <p class="text-secondary mb-0">Registra un corte operativo para consulta, control interno y trazabilidad.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">Periodo</label>
                <input type="text" name="period_code" class="form-control" value="<?= esc(date('Ym')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha inicial</label>
                <input type="date" name="start_date" class="form-control" value="<?= esc(date('Y-m-01')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha final</label>
                <input type="date" name="end_date" class="form-control" value="<?= esc(date('Y-m-t')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Deposito</label>
                <select name="warehouse_id" class="form-select">
                    <option value="">Global</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>"><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notas</label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
