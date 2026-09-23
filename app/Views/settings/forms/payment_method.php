<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/settings-forms.css') ?>">
<?php
$submitted = session()->getFlashdata('_ci_old_input')['post'] ?? null;
$values = is_array($submitted) ? $submitted : $method;
$selection = static function (string $key) use ($submitted, $method): array {
    $value = is_array($submitted) ? ($submitted[$key] ?? []) : json_decode($method[$key] ?? '[]', true);
    return is_array($value) ? $value : [];
};
$requiredOptions = ['referencia' => 'Referencia', 'entidad' => 'Entidad', 'fecha_acreditacion' => 'Fecha de acreditación'];
$required = $selection('required_fields');
$extra = is_array($submitted) ? ($submitted['extra_required_fields'] ?? '') : implode(', ', array_diff($required, array_keys($requiredOptions)));
?>
<div class="settings-form">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="mb-3"><h2 class="h5 mb-1"><?= esc($pageTitle) ?></h2><p class="text-secondary mb-0">Define las condiciones de uso del medio de pago para esta empresa.</p></div>
            <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
                <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
                <div class="col-md-4">
                    <label for="payment-code" class="form-label">Código</label>
                    <input id="payment-code" class="form-control" name="code" maxlength="30" pattern="[A-Za-z0-9_ \-]+" value="<?= esc($values['code'] ?? '') ?>" required>
                </div>
                <div class="col-md-8">
                    <label for="payment-name" class="form-label">Nombre</label>
                    <input id="payment-name" class="form-control" name="name" maxlength="120" value="<?= esc($values['name'] ?? '') ?>" required>
                </div>
                <?php foreach (['type' => ['Tipo', $types], 'funds_destination' => ['Destino de fondos', $destinations], 'active' => ['Estado', ['1' => 'Activo', '0' => 'Inactivo']], 'allows_installments' => ['Permite cuotas', ['0' => 'No', '1' => 'Sí']], 'requires_confirmation' => ['Requiere confirmación', ['0' => 'No', '1' => 'Sí']]] as $field => [$label, $options]): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="payment-<?= esc($field) ?>"><?= esc($label) ?></label>
                        <select class="form-select" id="payment-<?= esc($field) ?>" name="<?= esc($field) ?>" required>
                            <?php foreach ($options as $value => $text): ?>
                                <option value="<?= esc((string) $value) ?>" <?= (string) ($values[$field] ?? array_key_first($options)) === (string) $value ? 'selected' : '' ?>><?= esc($text) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-6">
                    <label for="payment-percentage" class="form-label">Porcentaje</label>
                    <div class="input-group">
                        <input id="payment-percentage" class="form-control" type="number" name="percentage" min="0" max="100" step="0.01" inputmode="decimal" value="<?= esc(is_array($submitted) ? (string) ($values['percentage'] ?? '') : number_format((float) ($method['percentage'] ?? 0), 2, '.', '')) ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <?php foreach (['currency_ids' => ['Monedas habilitadas', $currencies], 'branch_ids' => ['Sucursales habilitadas', $branches], 'point_of_sale_ids' => ['Puntos de venta habilitados', $points]] as $field => [$label, $options]): ?>
                    <fieldset class="col-12">
                        <legend class="form-label"><?= esc($label) ?></legend>
                        <p class="small text-secondary mb-2"><?= $field === 'currency_ids' ? 'Selecciona al menos una moneda.' : 'Sin selección: disponible en toda la empresa. Puedes limitarlo a sucursales y puntos de venta específicos.' ?></p>
                        <div class="border rounded-3 p-3 d-flex flex-wrap gap-3" style="max-height:180px;overflow:auto">
                            <?php foreach ($options as $option): ?>
                                <label class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="<?= esc($field) ?>[]" value="<?= esc($option['id']) ?>" <?= in_array($option['id'], $selection($field), true) ? 'checked' : '' ?>>
                                    <span class="form-check-label"><?= esc($option['name']) ?> (<?= esc($option['code']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (! $options): ?><span class="small text-secondary">No hay registros disponibles.</span><?php endif; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
                <fieldset class="col-12">
                    <legend class="form-label">Datos obligatorios</legend>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($requiredOptions as $key => $label): ?>
                            <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="required_fields[]" value="<?= esc($key) ?>" <?= in_array($key, $required, true) ? 'checked' : '' ?>><span class="form-check-label"><?= esc($label) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <label for="payment-extra" class="form-label mt-3">Otros datos obligatorios</label>
                    <input id="payment-extra" class="form-control" name="extra_required_fields" value="<?= esc(is_string($extra) ? $extra : '') ?>" placeholder="numero_cheque, titular" aria-describedby="payment-extra-help">
                    <div class="form-text" id="payment-extra-help">Identificadores sin espacios ni acentos, separados por comas.</div>
                </fieldset>
                <?php if (! empty($method['id'])): ?>
                    <div class="col-12 small text-secondary text-break">GUID: <?= esc($method['id']) ?></div>
                <?php endif; ?>
                <div class="col-12 d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-dark icon-btn" title="Guardar medio de pago" aria-label="Guardar medio de pago"><i class="bi bi-check-lg" aria-hidden="true"></i></button>
                    <a href="<?= site_url('configuracion?company_id=' . $companyId) ?>" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" <?php if ($isPopup): ?>onclick="event.preventDefault();window.parent.postMessage({type:'codex-popup-close'}, window.location.origin)"<?php endif; ?>><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
