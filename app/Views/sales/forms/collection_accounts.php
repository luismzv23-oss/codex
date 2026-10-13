<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $salesLive = false; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<section class="card"><div class="card-body p-4">
<h2 class="h5">Cuentas de cobranzas</h2>
<p class="text-secondary">Cada medio conserva su saldo hasta la acreditación manual. Los anticipos se registran como pasivo, sin generar una venta.</p>
<form method="post" action="<?= site_url('ventas/cobranzas/cuentas?company_id=' . $companyId . ($isPopup ? '&popup=1' : '')) ?>" class="row g-3">
<?= csrf_field() ?>
<?php foreach (\App\Libraries\CollectionAccountingService::ACCOUNTS as $key => $label): ?>
<label class="col-md-6"><?= esc($label) ?><select name="accounts[<?= esc($key) ?>]" class="form-select" required>
<option value="">Selecciona una cuenta</option>
<?php foreach ($accounts as $account): ?>
<?php if ($account['account_type'] !== ($key === 'customer_advances' ? 'liability' : 'asset')) continue; ?>
<option value="<?= esc($account['id']) ?>" <?= ($mapping[$key] ?? '') === $account['id'] ? 'selected' : '' ?>><?= esc($account['code'] . ' · ' . $account['name']) ?></option>
<?php endforeach; ?></select></label>
<?php endforeach; ?>
<div class="col-12"><button class="btn btn-dark icon-btn" title="Guardar cuentas" aria-label="Guardar cuentas"><i class="bi bi-check-lg"></i></button></div>
</form></div></section>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
