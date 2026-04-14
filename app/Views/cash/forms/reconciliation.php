<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Conciliacion</h2>
        <p class="text-secondary mb-0">Comparar el saldo esperado con el saldo real por medio de pago.</p>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3 mt-1">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Sesion</label><select name="cash_session_id" class="form-select" required><?php foreach ($sessions as $session): ?><option value="<?= esc($session['id']) ?>"><?= esc($session['register_name']) ?> / <?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Medio</label><select name="payment_method" class="form-select" required><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="cheque">Cheque</option><option value="qr">QR</option></select></div>
            <div class="col-md-6"><label class="form-label">Esperado</label><input type="number" step="0.01" name="expected_amount" class="form-control" value="0.00"></div>
            <div class="col-md-6"><label class="form-label">Real</label><input type="number" step="0.01" name="actual_amount" class="form-control" value="0.00"></div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
