<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
<h2 class="h5"><?= esc($pageTitle) ?></h2>
<p><?= esc(\App\Libraries\PaymentIntegrityService::label($payment)) ?> · <?= esc($payment['currency_code'] ?? '') ?> <?= number_format((float)$payment['amount'],2,',','.') ?></p>
<form method="post" action="<?= esc($formAction).($isPopup?'?popup=1':'') ?>" class="row g-3">
<?= csrf_field() ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
<?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
<div class="col-12"><label class="form-label" for="payment-evidence">Evidencia de confirmación</label><textarea id="payment-evidence" name="evidence" rows="3" maxlength="2000" class="form-control" required><?= esc(old('evidence')) ?></textarea></div>
<div class="col-12 d-flex gap-2">
<button class="btn btn-dark icon-btn" title="Confirmar" aria-label="Confirmar"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
<button type="button" class="btn btn-outline-dark icon-btn" title="Cerrar" aria-label="Cerrar" onclick="window.parent.postMessage({type:'codex-popup-close'},window.location.origin)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div></form></div></div>
<?= $this->endSection() ?>
