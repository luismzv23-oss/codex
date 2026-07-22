<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$isEdit  = ! empty($tax['id']);
$taxData = $tax ?? [];
?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1"><?= $isEdit ? 'Editar impuesto' : 'Nuevo impuesto' ?></h2>
            <p class="text-secondary mb-0"><?= $isEdit ? 'Modificar parámetros del impuesto registrado.' : 'Registra alícuotas y códigos impositivos para la empresa activa.' ?></p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <input type="hidden" name="company_id" value="<?= esc($companyId) ?>">
            
            <div class="col-md-5">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" value="<?= esc($taxData['name'] ?? '') ?>" required placeholder="Ej: IVA 21%">
            </div>
            <div class="col-md-3">
                <label class="form-label">Código</label>
                <input class="form-control" name="code" value="<?= esc($taxData['code'] ?? '') ?>" required placeholder="Ej: IVA21">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tasa (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" name="rate" value="<?= esc((string) ($taxData['rate'] ?? '21.00')) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Código AFIP (Opcional)</label>
                <select class="form-select" name="afip_code">
                    <option value="">Sin código AFIP</option>
                    <?php
                    $afipOptions = [
                        5 => '5 - IVA 21%',
                        4 => '4 - IVA 10.5%',
                        6 => '6 - IVA 27%',
                        8 => '8 - IVA 5%',
                        9 => '9 - IVA 2.5%',
                        3 => '3 - No Gravado (0%)',
                        2 => '2 - Exento',
                    ];
                    $currentAfip = isset($taxData['afip_code']) ? (int) $taxData['afip_code'] : null;
                    foreach ($afipOptions as $val => $label):
                    ?>
                        <option value="<?= $val ?>" <?= ($currentAfip === $val) ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Impuesto predeterminado</label>
                <select class="form-select" name="is_default">
                    <option value="0" <?= empty($taxData['is_default']) ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= ! empty($taxData['is_default']) ? 'selected' : '' ?>>Sí (Preseleccionar en facturas)</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select class="form-select" name="active">
                    <option value="1" <?= (isset($taxData['active']) && (int) $taxData['active'] === 1) || ! isset($taxData['active']) ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= isset($taxData['active']) && (int) $taxData['active'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>

            <div class="col-12 d-flex gap-2 pt-2">
                <button type="submit" class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i> Guardar</button>
                <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('configuracion?company_id=' . $companyId)) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
