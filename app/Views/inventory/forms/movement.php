<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $defaults = $defaults ?? []; ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Movimiento de stock</h2>
            <p class="text-secondary mb-0">Registra ingresos, egresos, transferencias o ajustes con trazabilidad completa.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3" id="inventory-movement-form">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Producto</label>
                <select name="product_id" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= esc($product['id']) ?>" <?= old('product_id', $defaults['product_id'] ?? '') === $product['id'] ? 'selected' : '' ?>><?= esc($product['sku'] . ' - ' . $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="movement_type" class="form-select" id="movement-type" required>
                    <?php foreach (['ingreso' => 'Ingreso', 'egreso' => 'Egreso', 'transferencia' => 'Transferencia', 'ajuste' => 'Ajuste'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= old('movement_type', $defaults['movement_type'] ?? 'ingreso') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cantidad</label>
                <input type="number" onkeypress="return event.charCode >= 48 && event.charCode <= 57" name="quantity" class="form-control" value="<?= esc(old('quantity', $defaults['quantity'] ?? '1')) ?>" required>
            </div>
            <div class="col-md-6 movement-source">
                <label class="form-label">Deposito origen</label>
                <select name="source_warehouse_id" class="form-select">
                    <option value="">Seleccionar</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>" <?= old('source_warehouse_id', $defaults['source_warehouse_id'] ?? '') === $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 movement-source-location">
                <label class="form-label">Ubicacion origen</label>
                <select name="source_location_id" class="form-select movement-location" data-warehouse-field="source_warehouse_id">
                    <option value="">Sin ubicacion</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= esc($location['id']) ?>" data-warehouse-id="<?= esc($location['warehouse_id']) ?>" <?= old('source_location_id', $defaults['source_location_id'] ?? '') === $location['id'] ? 'selected' : '' ?>><?= esc($location['name']) ?> (<?= esc($location['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 movement-destination">
                <label class="form-label">Deposito destino</label>
                <select name="destination_warehouse_id" class="form-select">
                    <option value="">Seleccionar</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>" <?= old('destination_warehouse_id', $defaults['destination_warehouse_id'] ?? '') === $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 movement-destination-location">
                <label class="form-label">Ubicacion destino</label>
                <select name="destination_location_id" class="form-select movement-location" data-warehouse-field="destination_warehouse_id">
                    <option value="">Sin ubicacion</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= esc($location['id']) ?>" data-warehouse-id="<?= esc($location['warehouse_id']) ?>" <?= old('destination_location_id', $defaults['destination_location_id'] ?? '') === $location['id'] ? 'selected' : '' ?>><?= esc($location['name']) ?> (<?= esc($location['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 movement-adjustment">
                <label class="form-label">Modo de ajuste</label>
                <select name="adjustment_mode" class="form-select">
                    <option value="">Seleccionar</option>
                    <option value="increase" <?= old('adjustment_mode', $defaults['adjustment_mode'] ?? '') === 'increase' ? 'selected' : '' ?>>Aumentar</option>
                    <option value="decrease" <?= old('adjustment_mode', $defaults['adjustment_mode'] ?? '') === 'decrease' ? 'selected' : '' ?>>Disminuir</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha y hora</label>
                <input type="datetime-local" name="occurred_at" class="form-control" value="<?= esc(old('occurred_at', $defaults['occurred_at'] ?? date('Y-m-d\TH:i'))) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Motivo</label>
                <input type="text" name="reason" class="form-control" value="<?= esc(old('reason', $defaults['reason'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Documento origen</label>
                <input type="text" name="source_document" class="form-control" value="<?= esc(old('source_document', $defaults['source_document'] ?? '')) ?>" placeholder="COMPRA-001 / VENTA-004 / AJUSTE">
            </div>
            <div class="col-md-3">
                <label class="form-label">Costo unitario</label>
                <input type="number" step="0.0001" min="0" name="unit_cost" class="form-control" value="<?= esc(old('unit_cost', $defaults['unit_cost'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Lote</label>
                <input type="text" name="lot_number" class="form-control" value="<?= esc(old('lot_number', $defaults['lot_number'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Serie</label>
                <input type="text" name="serial_number" class="form-control" value="<?= esc(old('serial_number', $defaults['serial_number'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Vencimiento</label>
                <input type="date" name="expiration_date" class="form-control" value="<?= esc(old('expiration_date', $defaults['expiration_date'] ?? '')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Observacion</label>
                <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes', $defaults['notes'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>

<script>
    (() => {
        const typeField = document.getElementById('movement-type');
        const source = document.querySelector('.movement-source');
        const sourceLocation = document.querySelector('.movement-source-location');
        const destination = document.querySelector('.movement-destination');
        const destinationLocation = document.querySelector('.movement-destination-location');
        const adjustment = document.querySelector('.movement-adjustment');
        if (!typeField || !source || !sourceLocation || !destination || !destinationLocation || !adjustment) return;
        const filterLocationOptions = (warehouseFieldName) => {
            const warehouseField = document.querySelector(`[name="${warehouseFieldName}"]`);
            const locationField = document.querySelector(`.movement-location[data-warehouse-field="${warehouseFieldName}"]`);
            if (!warehouseField || !locationField) return;
            const warehouseId = warehouseField.value;
            locationField.querySelectorAll('option[data-warehouse-id]').forEach((option) => {
                option.hidden = warehouseId !== '' && option.dataset.warehouseId !== warehouseId;
            });
            if (locationField.selectedOptions[0]?.hidden) {
                locationField.value = '';
            }
        };
        const sync = () => {
            const value = typeField.value;
            source.style.display = ['egreso', 'transferencia', 'ajuste'].includes(value) ? '' : 'none';
            sourceLocation.style.display = ['egreso', 'transferencia', 'ajuste'].includes(value) ? '' : 'none';
            destination.style.display = ['ingreso', 'transferencia'].includes(value) ? '' : 'none';
            destinationLocation.style.display = ['ingreso', 'transferencia'].includes(value) ? '' : 'none';
            adjustment.style.display = value === 'ajuste' ? '' : 'none';
            filterLocationOptions('source_warehouse_id');
            filterLocationOptions('destination_warehouse_id');
        };
        document.querySelector('[name="source_warehouse_id"]')?.addEventListener('change', () => filterLocationOptions('source_warehouse_id'));
        document.querySelector('[name="destination_warehouse_id"]')?.addEventListener('change', () => filterLocationOptions('destination_warehouse_id'));
        typeField.addEventListener('change', sync);
        sync();
    })();
</script>
<?= $this->endSection() ?>
