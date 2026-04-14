<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Promocion</h2>
            <p class="text-secondary mb-0">Crea descuentos por porcentaje o monto fijo para ventas.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Tipo</label><select name="promotion_type" class="form-select"><option value="percent">Porcentaje</option><option value="fixed">Monto fijo</option><option value="buy_x_get_y">AXB / 2x1</option><option value="quantity_scale">Escala por cantidad</option><option value="bundle_price">Precio combo</option></select></div>
            <div class="col-md-3"><label class="form-label">Valor</label><input type="number" step="0.01" min="0" name="value" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Alcance</label><select name="scope" class="form-select"><option value="selected">Productos seleccionados</option><option value="all">Todos los productos</option></select></div>
            <div class="col-md-4"><label class="form-label">Inicio</label><input type="datetime-local" name="start_date" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Fin</label><input type="datetime-local" name="end_date" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Dispara cantidad</label><input type="number" step="0.01" min="0" name="trigger_quantity" class="form-control" value="0"></div>
            <div class="col-md-3"><label class="form-label">Bonifica cantidad</label><input type="number" step="0.01" min="0" name="bonus_quantity" class="form-control" value="0"></div>
            <div class="col-md-3"><label class="form-label">Producto bonificado</label><select name="bonus_product_id" class="form-select"><option value="">Mismo producto</option><?php foreach ($products as $product): ?><option value="<?= esc($product['id']) ?>"><?= esc($product['sku'] . ' - ' . $product['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Precio combo</label><input type="number" step="0.01" min="0" name="bundle_price" class="form-control" value="0"></div>
            <div class="col-md-4"><label class="form-label">Medio de pago</label><select name="payment_method" class="form-select"><option value="">Todos</option><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="qr">QR</option><?php foreach ($gateways as $gateway): ?><option value="<?= esc($gateway['code']) ?>"><?= esc($gateway['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Estado</label><select name="active" class="form-select"><option value="1">Activa</option><option value="0">Inactiva</option></select></div>
            <div class="col-12"><label class="form-label">Descripcion</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="col-12">
                <label class="form-label">Productos</label>
                <select name="product_ids[]" class="form-select" multiple size="8">
                    <?php foreach ($products as $product): ?>
                        <option value="<?= esc($product['id']) ?>"><?= esc($product['sku'] . ' - ' . $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
