<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
<h2 class="h5 mb-1">Conciliación</h2>
<p class="text-secondary">Compara importes por medio y moneda. El efectivo de apertura figura por separado.</p>
<form id="treasury-reconciliation" method="post" action="<?= esc($formAction).($isPopup?'?popup=1':'') ?>" class="row g-3"
 data-preview="<?= esc(site_url('caja/conciliaciones/saldos')) ?>">
<?= csrf_field() ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
<?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
<div class="col-md-6"><label class="form-label">Sesión</label><select name="cash_session_id" class="form-select" required>
<?php foreach ($sessions as $cashSession): ?><option value="<?= esc($cashSession['id']) ?>" <?= old('cash_session_id')===$cashSession['id']?'selected':'' ?>><?= esc($cashSession['register_name'].' / '.$cashSession['opened_at']) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-6"><label class="form-label" for="reconciliation-medium">Medio y moneda</label><select id="reconciliation-medium" class="form-select" required></select></div>
<input type="hidden" name="reconciliation_key" value="<?= esc(old('reconciliation_key')) ?>"><input type="hidden" name="currency_code" value="<?= esc(old('currency_code')) ?>">
<div class="col-md-6"><label class="form-label">Esperado calculado</label><input id="reconciliation-expected" class="form-control" readonly></div>
<div class="col-md-6"><label class="form-label">Real verificado</label><input type="number" step="0.01" name="actual_amount" class="form-control" required value="<?= esc(old('actual_amount') ?? '') ?>"></div>
<div id="reconciliation-status" class="col-12 small text-secondary" role="status"></div>
<div class="col-12"><label class="form-label">Notas</label><textarea name="notes" class="form-control" rows="2"><?= esc(old('notes')) ?></textarea></div>
<div class="col-12 d-flex gap-2">
<button id="reconciliation-save" class="btn btn-dark icon-btn" title="Guardar conciliación" aria-label="Guardar conciliación" disabled><i class="bi bi-check-lg"></i></button>
<button id="reconciliation-refresh" type="button" class="btn btn-outline-dark icon-btn" title="Actualizar saldo" aria-label="Actualizar saldo"><i class="bi bi-arrow-clockwise"></i></button>
<button type="button" class="btn btn-outline-dark icon-btn" title="Cerrar" aria-label="Cerrar" onclick="window.parent.postMessage({type:'codex-popup-close'},window.location.origin)"><i class="bi bi-x-lg"></i></button>
</div></form></div></div>
<script src="<?= base_url('assets/js/treasury-reconciliation.js') ?>" defer></script>
<?= $this->endSection() ?>
