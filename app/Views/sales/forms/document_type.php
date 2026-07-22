<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$isEdit  = ! empty($documentType['id']);
$docData = $documentType ?? [];
?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1"><?= $isEdit ? 'Editar comprobante' : 'Nuevo comprobante' ?></h2>
            <p class="text-secondary mb-0"><?= $isEdit ? 'Modificar parámetros del tipo de comprobante registrado.' : 'Define un nuevo tipo de comprobante para las operaciones de venta de la empresa.' ?></p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>

            <div class="col-md-6">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="name" value="<?= esc($docData['name'] ?? '') ?>" required placeholder="Ej: Factura A, Presupuesto">
            </div>

            <div class="col-md-3">
                <label class="form-label">Código</label>
                <input class="form-control" name="code" value="<?= esc($docData['code'] ?? '') ?>" required placeholder="Ej: FACTURA_A">
            </div>

            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="category" required>
                    <?php
                    $categories = [
                        'invoice'       => 'Factura / Comprobante',
                        'quote'         => 'Presupuesto',
                        'order'         => 'Pedido',
                        'delivery_note' => 'Remito',
                        'credit_note'   => 'Nota de Crédito',
                        'debit_note'    => 'Nota de Débito',
                    ];
                    $currentCat = $docData['category'] ?? 'invoice';
                    foreach ($categories as $catVal => $catLabel):
                    ?>
                        <option value="<?= $catVal ?>" <?= ($currentCat === $catVal) ? 'selected' : '' ?>><?= esc($catLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Letra (Opcional)</label>
                <input class="form-control" name="letter" value="<?= esc($docData['letter'] ?? '') ?>" placeholder="Ej: A, B, C, X">
            </div>

            <div class="col-md-3">
                <label class="form-label">Clave de Secuencia</label>
                <input class="form-control" name="sequence_key" value="<?= esc($docData['sequence_key'] ?? '') ?>" required placeholder="Ej: FACTURA_A">
            </div>

            <div class="col-md-3">
                <label class="form-label">Prefijo por defecto</label>
                <input class="form-control" name="default_prefix" value="<?= esc($docData['default_prefix'] ?? '') ?>" placeholder="Ej: FCA">
            </div>

            <div class="col-md-3">
                <label class="form-label">Canal</label>
                <select class="form-select" name="channel">
                    <option value="standard" <?= ($docData['channel'] ?? 'standard') === 'standard' ? 'selected' : '' ?>>Estándar / Web</option>
                    <option value="kiosk" <?= ($docData['channel'] ?? '') === 'kiosk' ? 'selected' : '' ?>>Kiosco / POS</option>
                    <option value="both" <?= ($docData['channel'] ?? '') === 'both' ? 'selected' : '' ?>>Ambos canales</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">¿Impacta Stock?</label>
                <select class="form-select" name="impacts_stock">
                    <option value="1" <?= (isset($docData['impacts_stock']) && (int) $docData['impacts_stock'] === 1) || ! isset($docData['impacts_stock']) ? 'selected' : '' ?>>Sí (Descuenta stock)</option>
                    <option value="0" <?= isset($docData['impacts_stock']) && (int) $docData['impacts_stock'] === 0 ? 'selected' : '' ?>>No (Sin stock)</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">¿Impacta Cta Cte / Cobranza?</label>
                <select class="form-select" name="impacts_receivable">
                    <option value="1" <?= (isset($docData['impacts_receivable']) && (int) $docData['impacts_receivable'] === 1) || ! isset($docData['impacts_receivable']) ? 'selected' : '' ?>>Sí</option>
                    <option value="0" <?= isset($docData['impacts_receivable']) && (int) $docData['impacts_receivable'] === 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">¿Requiere Cliente Obligatorio?</label>
                <select class="form-select" name="requires_customer">
                    <option value="0" <?= empty($docData['requires_customer']) ? 'selected' : '' ?>>No (Opcional)</option>
                    <option value="1" <?= ! empty($docData['requires_customer']) ? 'selected' : '' ?>>Sí (Obligatorio)</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Orden de Visualización</label>
                <input type="number" min="1" class="form-control" name="sort_order" value="<?= esc((string) ($docData['sort_order'] ?? '1')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Comprobante Predeterminado</label>
                <select class="form-select" name="is_default">
                    <option value="0" <?= empty($docData['is_default']) ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= ! empty($docData['is_default']) ? 'selected' : '' ?>>Sí (Preseleccionar en facturas)</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select class="form-select" name="active">
                    <option value="1" <?= (isset($docData['active']) && (int) $docData['active'] === 1) || ! isset($docData['active']) ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= isset($docData['active']) && (int) $docData['active'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>

            <div class="col-12 d-flex gap-2 pt-2">
                <button type="submit" class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i> Guardar</button>
                <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('ventas/configuracion?company_id=' . ($companyId ?? ''))) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i> Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
