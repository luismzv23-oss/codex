<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $salesLive = false; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<section class="card border-0 rounded-4"><div class="card-body p-4">
<h2 class="h5">Anular remito · <?= esc($note['number']) ?></h2>
<p><?= esc($note['customer_name_snapshot'] ?? 'Consumidor Final') ?> · <?= esc($note['billing_status']) ?></p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif; ?>
<?php if ($note['cancel_reason']): ?><div class="alert alert-warning"><?= esc($note['cancel_reason']) ?></div><?php endif; ?>
<div class="alert alert-info"><?= $note['stock_recorded'] ? 'La anulación reingresará las cantidades de las salidas originales al depósito de origen. Debes confirmar que toda la mercadería regresó físicamente.' : 'Este remito no registra una salida de stock. Su anulación no reingresará mercadería.' ?></div>
<table class="table"><thead><tr><th>Producto</th><th>Cantidad</th></tr></thead><tbody>
<?php foreach ($note['items'] as $item): ?><tr><td><?= esc($item['product_name']) ?></td><td><?= esc($item['quantity']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<form method="post" action="<?= esc($formAction) ?>">
<?= csrf_field() ?>
<input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><input type="hidden" name="popup" value="<?= $isPopup ? '1' : '0' ?>">
<label class="form-label w-100">Motivo de anulación<textarea class="form-control" name="cancellation_reason" required maxlength="1000" rows="3"><?= esc($input['cancellation_reason'] ?? '') ?></textarea></label>
<?php if ($note['stock_recorded']): ?><label class="d-block my-3"><input class="form-check-input me-2" type="checkbox" name="return_confirmed" value="1" required <?= ($input['return_confirmed'] ?? '') === '1' ? 'checked' : '' ?>>Confirmo que toda la mercadería del remito regresó al depósito de origen.</label><?php endif; ?>
<div class="text-end"><button class="btn btn-danger icon-btn" title="Confirmar anulación" aria-label="Confirmar anulación" <?= $note['cancel_reason'] ? 'disabled' : '' ?>><i class="bi bi-x-circle" aria-hidden="true"></i></button></div>
</form>
<?= view('sales/delivery_invoice_history', ['note' => $note, 'companyId' => $companyId]) ?>
</div></section>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
