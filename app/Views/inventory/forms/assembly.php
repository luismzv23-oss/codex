<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Ensamble o desensamble</h2>
            <p class="text-secondary mb-0">Transforma kits en producto final o desarma producto compuesto en sus componentes.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6">
                <label class="form-label">Producto principal</label>
                <select name="product_id" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= esc($product['id']) ?>"><?= esc($product['sku'] . ' - ' . $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Operacion</label>
                <select name="assembly_type" class="form-select">
                    <option value="assembly">Ensamble</option>
                    <option value="disassembly">Desensamble</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cantidad</label>
                <input type="number" name="quantity" class="form-control" min="0.0001" step="0.0001" value="1" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Deposito</label>
                <select name="warehouse_id" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>"><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha y hora</label>
                <input type="datetime-local" name="issued_at" class="form-control" value="<?= esc(date('Y-m-d\TH:i')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn" title="Guardar" aria-label="Guardar"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)" title="Cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
