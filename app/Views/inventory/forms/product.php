<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1"><?= empty($product) ? 'Producto nuevo' : 'Editar producto' ?></h2>
            <p class="text-secondary mb-0">Completa los datos base del producto para control de stock y trazabilidad.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control text-uppercase" value="<?= esc(old('sku', $product['sku'] ?? '')) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Producto</label>
                <input type="text" name="name" class="form-control" value="<?= esc(old('name', $product['name'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Categoria</label>
                <input type="text" name="category" class="form-control" value="<?= esc(old('category', $product['category'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Marca</label>
                <input type="text" name="brand" class="form-control" value="<?= esc(old('brand', $product['brand'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Codigo de barras</label>
                <input type="text" name="barcode" class="form-control" value="<?= esc(old('barcode', $product['barcode'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo de producto</label>
                <select name="product_type" class="form-select">
                    <option value="simple" <?= old('product_type', $product['product_type'] ?? 'simple') === 'simple' ? 'selected' : '' ?>>Simple</option>
                    <option value="kit" <?= old('product_type', $product['product_type'] ?? 'simple') === 'kit' ? 'selected' : '' ?>>Compuesto / Kit</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Unidad</label>
                <input type="text" name="unit" class="form-control" value="<?= esc(old('unit', $product['unit'] ?? 'unidad')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Stock minimo</label>
                <input type="number" step="0.01" min="0" name="min_stock" class="form-control" value="<?= esc(old('min_stock', $product['min_stock'] ?? '0')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Stock maximo</label>
                <input type="number" step="0.01" min="0" name="max_stock" class="form-control" value="<?= esc(old('max_stock', $product['max_stock'] ?? '0')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Precio unitario de compra</label>
                <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= esc(old('cost_price', $product['cost_price'] ?? '0')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Precio unitario de venta</label>
                <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="<?= esc(old('sale_price', $product['sale_price'] ?? '0')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Control de lote</label>
                <select name="lot_control" class="form-select">
                    <option value="0" <?= (string) old('lot_control', (string) ($product['lot_control'] ?? '0')) === '0' ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= (string) old('lot_control', (string) ($product['lot_control'] ?? '0')) === '1' ? 'selected' : '' ?>>Si</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Control de serie</label>
                <select name="serial_control" class="form-select">
                    <option value="0" <?= (string) old('serial_control', (string) ($product['serial_control'] ?? '0')) === '0' ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= (string) old('serial_control', (string) ($product['serial_control'] ?? '0')) === '1' ? 'selected' : '' ?>>Si</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Control de vencimiento</label>
                <select name="expiration_control" class="form-select">
                    <option value="0" <?= (string) old('expiration_control', (string) ($product['expiration_control'] ?? '0')) === '0' ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= (string) old('expiration_control', (string) ($product['expiration_control'] ?? '0')) === '1' ? 'selected' : '' ?>>Si</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select name="active" class="form-select">
                    <option value="1" <?= (string) old('active', (string) ($product['active'] ?? '1')) === '1' ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= (string) old('active', (string) ($product['active'] ?? '1')) === '0' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Descripcion</label>
                <textarea name="description" class="form-control" rows="3"><?= esc(old('description', $product['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <div class="border rounded-4 p-3">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h3 class="h6 mb-1">Composicion del kit</h3>
                            <p class="text-secondary mb-0">Solo aplica cuando el producto se define como kit o compuesto.</p>
                        </div>
                    </div>
                    <div class="row g-2">
                        <?php $rows = old('component_product_id') ? array_map(null, (array) old('component_product_id'), (array) old('component_quantity')) : array_map(static fn($row) => [$row['component_product_id'], $row['quantity']], $kitItems ?? []); ?>
                        <?php if ($rows === []): $rows = [[null, null], [null, null], [null, null]]; endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <div class="col-md-8">
                                <select name="component_product_id[]" class="form-select">
                                    <option value="">Componente</option>
                                    <?php foreach ($componentOptions as $component): ?>
                                        <option value="<?= esc($component['id']) ?>" <?= (string) ($row[0] ?? '') === (string) $component['id'] ? 'selected' : '' ?>><?= esc($component['sku'] . ' - ' . $component['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.0001" min="0" name="component_quantity[]" class="form-control" placeholder="Cantidad" value="<?= esc($row[1] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
