<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Movimiento de caja</h1>
        <p class="text-secondary mb-4">Registra ingresos, egresos, retiros o ajustes manuales sobre una sesion activa.</p>
        <form method="post" action="<?= esc($formAction).($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Sesion activa</label>
                <select name="cash_session_id" class="form-select" required>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?= esc($session['id']) ?>" <?= old('cash_session_id')===$session['id']?'selected':'' ?>><?= esc($session['register_name']) ?> - <?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="movement_type" class="form-select">
                    <option value="manual_income" <?= old('movement_type')==='manual_income'?'selected':'' ?>>Ingreso</option>
                    <option value="manual_expense" <?= old('movement_type')==='manual_expense'?'selected':'' ?>>Egreso</option>
                    <option value="withdrawal" <?= old('movement_type')==='withdrawal'?'selected':'' ?>>Retiro</option>
                    <option value="adjustment" <?= old('movement_type')==='adjustment'?'selected':'' ?>>Ajuste</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Monto</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?= esc(old('amount')) ?>">
            </div>
            <?php
            $sessionBranches=[];
            foreach ($sessions as $cashSession) {
                $sessionBranches[$cashSession['id']]=$registerBranches[$cashSession['cash_register_id']] ?? null;
            }
            ?>
            <?= view('shared/treasury_payment_fields',get_defined_vars()) ?>
            <div class="col-md-4"><label class="form-label">Sentido del ajuste</label><select class="form-select" name="adjustment_direction"><option value="income">Ingreso</option><option value="expense" <?= old('adjustment_direction')==='expense'?'selected':'' ?>>Egreso</option></select></div>
            <div class="col-md-4">
                <label class="form-label">Referencia</label>
                <input type="text" name="reference" class="form-control" value="<?= esc(old('reference')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha</label>
                <input type="datetime-local" name="paid_at" class="form-control" value="<?= esc(old('paid_at') ?: date('Y-m-d\TH:i')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark icon-btn" title="Guardar movimiento" aria-label="Guardar movimiento"><i class="bi bi-check-lg"></i></button>
                <a href="<?= site_url('caja' . (! empty($companyId) ? '?company_id=' . $companyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Cancelar" aria-label="Cancelar" <?php if ($isPopup): ?>onclick="event.preventDefault();window.parent.postMessage({type:'codex-popup-close'},window.location.origin)"<?php endif; ?>><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
