<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Lista de precios</h2>
            <p class="text-secondary mb-0">Define precios comerciales específicos por producto para ventas y POS.</p>
        </div>
        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-3">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="name" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Predeterminada</label><select name="is_default" class="form-select"><option value="0">No</option><option value="1">Si</option></select></div>
            <div class="col-md-3"><label class="form-label">Estado</label><select name="active" class="form-select"><option value="1">Activa</option><option value="0">Inactiva</option></select></div>
            <div class="col-12"><label class="form-label">Descripcion</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Producto</th><th>Precio lista</th></tr></thead>
                        <tbody>
                            <?php foreach ($products as $index => $product): ?>
                                <tr>
                                    <td>
                                        <?= esc($product['sku'] . ' - ' . $product['name']) ?>
                                        <input type="hidden" name="items[<?= $index ?>][product_id]" value="<?= esc($product['id']) ?>">
                                    </td>
                                    <td><input type="number" step="0.01" min="0" name="items[<?= $index ?>][price]" class="form-control" value="<?= esc(number_format((float) ($product['sale_price'] ?? 0), 2, '.', '')) ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 d-flex gap-2 pt-2">
                <button class="btn btn-dark icon-btn"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-outline-dark icon-btn" onclick="window.parent.postMessage({ type: 'codex-popup-close' }, window.location.origin)"><i class="bi bi-x-lg"></i></button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
