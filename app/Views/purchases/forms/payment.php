<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $purchaseEditing = true; ?>
<?php if (!$isPopup): ?><?= view('purchases/shell', get_defined_vars()) ?><?php endif; ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Pago a proveedor</h2>
            <p class="text-secondary mb-0">Registrar pago imputado sobre la cuenta seleccionada.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <input type="hidden" name="purchase_payable_id" value="<?= esc($payable['id']) ?>">
            <div class="col-md-4"><label class="form-label">Cuenta</label><input type="text" class="form-control" value="<?= esc($payable['payable_number']) ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Proveedor</label><input type="text" class="form-control" value="<?= esc($supplier['name'] ?? '-') ?>" readonly></div>
            <div class="col-md-4"><label class="form-label">Saldo</label><input type="text" class="form-control" value="<?= esc(number_format((float) $payable['balance_amount'], 2, ',', '.')) ?>" readonly></div>
            <?php $treasuryOperation='supplier'; $fixedCurrency=$payable['currency_code']; $defaultExchangeRate=$payable['exchange_rate'] ?? 1; ?>
            <?= view('shared/treasury_payment_fields',get_defined_vars()) ?>
            <div class="col-md-3"><label class="form-label">Gateway</label><select name="gateway_id" class="form-select"><option value="">Sin gateway</option><?php foreach ($gateways as $gateway): ?><option value="<?= esc($gateway['id']) ?>" <?= old('gateway_id')===$gateway['id']?'selected':'' ?>><?= esc($gateway['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Cheque</label><select name="cash_check_id" class="form-select"><option value="">Sin cheque</option><?php foreach ($checks as $check): ?><option value="<?= esc($check['id']) ?>" <?= old('cash_check_id')===$check['id']?'selected':'' ?>><?= esc($check['check_number'] . ' / ' . $check['bank_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Monto</label><input type="number" step="0.01" min="0.01" max="<?= esc(number_format((float) $payable['balance_amount'], 2, '.', '')) ?>" name="amount" class="form-control" value="<?= esc(old('amount') ?? number_format((float) $payable['balance_amount'], 2, '.', '')) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Fecha</label><input type="datetime-local" name="paid_at" class="form-control" value="<?= esc(old('paid_at') ?: date('Y-m-d\TH:i')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Referencia</label><input type="text" name="reference" class="form-control" value="<?= esc(old('reference')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Referencia externa</label><input type="text" name="external_reference" class="form-control" value="<?= esc(old('external_reference')) ?>"></div>
            <div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea></div>
            <div class="col-12 d-flex gap-2 pt-2"><button class="btn btn-dark icon-btn" title="Registrar pago" aria-label="Registrar pago"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-outline-dark icon-btn" title="Cerrar" aria-label="Cerrar" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button></div>
        </form>
    </div>
</div>
<?php if (!$isPopup): ?><?= view('purchases/end') ?><?php endif; ?>
<?= $this->endSection() ?>
