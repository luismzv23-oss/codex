<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Parametros de inventario</h2>
            <p class="text-secondary mb-0">Configura alertas, umbrales operativos y comportamiento del stock del sistema.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-12">
                <label class="form-label">Email de alertas</label>
                <input type="email" name="alert_email" class="form-control" value="<?= esc(old('alert_email', $settings['alert_email'] ?? $company['email'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Umbral de movimiento inusual</label>
                <input type="number" step="0.01" min="0" name="unusual_movement_threshold" class="form-control" value="<?= esc(old('unusual_movement_threshold', $settings['unusual_movement_threshold'] ?? '100')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Dias sin rotacion</label>
                <input type="number" min="1" name="no_rotation_days" class="form-control" value="<?= esc(old('no_rotation_days', $settings['no_rotation_days'] ?? '30')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Stock negativo</label>
                <select name="allow_negative_stock" class="form-select">
                    <option value="0" <?= (int) old('allow_negative_stock', $settings['allow_negative_stock'] ?? 0) === 0 ? 'selected' : '' ?>>Bloquear</option>
                    <option value="1" <?= (int) old('allow_negative_stock', $settings['allow_negative_stock'] ?? 0) === 1 ? 'selected' : '' ?>>Permitir</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Alertas de stock minimo</label>
                <select name="low_stock_alerts" class="form-select">
                    <option value="1" <?= (int) old('low_stock_alerts', $settings['low_stock_alerts'] ?? 1) === 1 ? 'selected' : '' ?>>Activas</option>
                    <option value="0" <?= (int) old('low_stock_alerts', $settings['low_stock_alerts'] ?? 1) === 0 ? 'selected' : '' ?>>Inactivas</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notificaciones internas</label>
                <select name="internal_notifications" class="form-select">
                    <option value="1" <?= (int) old('internal_notifications', $settings['internal_notifications'] ?? 1) === 1 ? 'selected' : '' ?>>Activas</option>
                    <option value="0" <?= (int) old('internal_notifications', $settings['internal_notifications'] ?? 1) === 0 ? 'selected' : '' ?>>Inactivas</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notificaciones por email</label>
                <select name="email_notifications" class="form-select">
                    <option value="0" <?= (int) old('email_notifications', $settings['email_notifications'] ?? 0) === 0 ? 'selected' : '' ?>>Inactivas</option>
                    <option value="1" <?= (int) old('email_notifications', $settings['email_notifications'] ?? 0) === 1 ? 'selected' : '' ?>>Activas</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Metodo de costeo</label>
                <select name="valuation_method" class="form-select">
                    <option value="fifo" <?= old('valuation_method', $settings['valuation_method'] ?? 'weighted_average') === 'fifo' ? 'selected' : '' ?>>FIFO</option>
                    <option value="lifo" <?= old('valuation_method', $settings['valuation_method'] ?? 'weighted_average') === 'lifo' ? 'selected' : '' ?>>LIFO</option>
                    <option value="weighted_average" <?= old('valuation_method', $settings['valuation_method'] ?? 'weighted_average') === 'weighted_average' ? 'selected' : '' ?>>Promedio ponderado</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Alcance de stock negativo</label>
                <select name="negative_stock_scope" class="form-select">
                    <option value="global" <?= old('negative_stock_scope', $settings['negative_stock_scope'] ?? 'global') === 'global' ? 'selected' : '' ?>>Global</option>
                    <option value="by_document" <?= old('negative_stock_scope', $settings['negative_stock_scope'] ?? 'global') === 'by_document' ? 'selected' : '' ?>>Por documento</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Negativo en ventas</label>
                <select name="allow_negative_on_sales" class="form-select">
                    <option value="0" <?= (int) old('allow_negative_on_sales', $settings['allow_negative_on_sales'] ?? 0) === 0 ? 'selected' : '' ?>>Bloquear</option>
                    <option value="1" <?= (int) old('allow_negative_on_sales', $settings['allow_negative_on_sales'] ?? 0) === 1 ? 'selected' : '' ?>>Permitir</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Negativo en transferencias</label>
                <select name="allow_negative_on_transfers" class="form-select">
                    <option value="0" <?= (int) old('allow_negative_on_transfers', $settings['allow_negative_on_transfers'] ?? 0) === 0 ? 'selected' : '' ?>>Bloquear</option>
                    <option value="1" <?= (int) old('allow_negative_on_transfers', $settings['allow_negative_on_transfers'] ?? 0) === 1 ? 'selected' : '' ?>>Permitir</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Negativo en ajustes</label>
                <select name="allow_negative_on_adjustments" class="form-select">
                    <option value="0" <?= (int) old('allow_negative_on_adjustments', $settings['allow_negative_on_adjustments'] ?? 0) === 0 ? 'selected' : '' ?>>Bloquear</option>
                    <option value="1" <?= (int) old('allow_negative_on_adjustments', $settings['allow_negative_on_adjustments'] ?? 0) === 1 ? 'selected' : '' ?>>Permitir</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
