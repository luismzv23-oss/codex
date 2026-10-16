<?php
$treasuryCatalog=$paymentCatalog ?? [];
$treasuryCurrencies=$paymentCurrencies ?? [];
$treasuryPoints=$paymentPoints ?? [];
$treasuryOld=session()->getFlashdata('_ci_old_input')['post'] ?? [];
$treasuryCurrency=$fixedCurrency ?? ($treasuryOld['currency_code'] ?? ($baseCurrency ?? 'ARS'));
$treasuryConfig=['catalog'=>$treasuryCatalog,'operation'=>$treasuryOperation ?? 'collection','branchId'=>$paymentBranchId ?? null,
    'sessionBranches'=>$sessionBranches ?? [],'old'=>$treasuryOld];
?>
<input type="hidden" name="request_key" value="<?= esc($treasuryOld['request_key'] ?? app_uuid()) ?>">
<div class="col-md-6"><label class="form-label" for="treasury-method">Medio de pago</label>
<select id="treasury-method" name="payment_method_id" class="form-select" required>
<option value="">Selecciona un medio</option>
<?php foreach ($treasuryCatalog as $method): ?><option value="<?= esc($method['id']) ?>" <?= ($treasuryOld['payment_method_id'] ?? '')===$method['id']?'selected':'' ?>><?= esc($method['code'].' · '.$method['name']) ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-3"><label class="form-label" for="treasury-currency">Moneda</label>
<select id="treasury-currency" name="currency_code" class="form-select" required>
<?php foreach ($treasuryCurrencies as $currency): if (isset($fixedCurrency) && $currency['code']!==$fixedCurrency) continue; ?>
<option value="<?= esc($currency['code']) ?>" data-id="<?= esc($currency['id']) ?>" <?= $treasuryCurrency===$currency['code']?'selected':'' ?>><?= esc($currency['code']) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Cotización</label><input type="number" class="form-control" name="exchange_rate" min="0.000001" step="0.000001" required value="<?= esc($treasuryOld['exchange_rate'] ?? $defaultExchangeRate ?? 1) ?>"></div>
<div class="col-md-6"><label class="form-label" for="treasury-point">Punto de venta</label>
<select id="treasury-point" class="form-select" name="point_of_sale_id"><option value="">Sin punto de venta</option>
<?php foreach ($treasuryPoints as $point): ?><option value="<?= esc($point['id']) ?>" data-branch="<?= esc($point['branch_id'] ?? '') ?>" <?= ($treasuryOld['point_of_sale_id'] ?? '')===$point['id']?'selected':'' ?>><?= esc($point['code'].' · '.$point['name']) ?></option><?php endforeach; ?>
</select><div class="form-text">Necesario cuando el medio está limitado a un punto de venta.</div></div>
<div class="col-12 text-secondary small" id="treasury-policy" role="status"></div>
<div class="col-12 row g-3 m-0 p-0" id="treasury-required"></div>
<script id="treasury-config" type="application/json"><?= json_encode($treasuryConfig,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="<?= base_url('assets/js/treasury-payments.js') ?>" defer></script>
