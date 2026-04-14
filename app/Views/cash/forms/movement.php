<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Movimiento de caja</h1>
        <p class="text-secondary mb-4">Registra ingresos, egresos, retiros o ajustes manuales sobre una sesion activa.</p>
        <form method="post" action="<?= esc($formAction) ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Sesion activa</label>
                <select name="cash_session_id" class="form-select" required>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= esc($session['id']) ?>"><?= esc($session['register_name']) ?> - <?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="movement_type" class="form-select">
                    <option value="manual_income">Ingreso</option>
                    <option value="manual_expense">Egreso</option>
                    <option value="withdrawal">Retiro</option>
                    <option value="adjustment">Ajuste</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Monto</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Metodo</label>
                <select name="payment_method" class="form-select">
                    <option value="cash">Efectivo</option>
                    <option value="card">Tarjeta</option>
                    <option value="transfer">Transferencia</option>
                    <option value="mixed">Mixto</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Referencia</label>
                <input type="text" name="reference_number" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha</label>
                <input type="datetime-local" name="occurred_at" class="form-control" value="<?= esc(date('Y-m-d\TH:i')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark icon-btn" title="Guardar movimiento" aria-label="Guardar movimiento"><i class="bi bi-check-lg"></i></button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
