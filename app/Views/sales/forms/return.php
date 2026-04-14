<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="mb-3">
            <h2 class="h5 mb-1">Registrar devolucion</h2>
            <p class="text-secondary mb-0">Reingresa stock, deja trazabilidad con la venta original y emite la nota de credito interna.</p>
        </div>

        <div class="border rounded-4 p-3 bg-light-subtle mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="small text-secondary">Venta</div>
                    <div class="fw-semibold"><?= esc($sale['sale_number']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-secondary">Estado</div>
                    <div class="fw-semibold"><?= esc($sale['status']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="small text-secondary">Total</div>
                    <div class="fw-semibold"><?= number_format((float) ($sale['total'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" class="row g-4">
            <?= csrf_field() ?>
            <?php if ($isPopup): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
            <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>

            <div class="col-md-6">
                <label class="form-label">Deposito de reingreso</label>
                <select name="warehouse_id" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>" <?= old('warehouse_id', $sale['warehouse_id'] ?? '') === $warehouse['id'] ? 'selected' : '' ?>>
                            <?= esc($warehouse['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Motivo general</label>
                <input type="text" name="reason" class="form-control" value="<?= esc(old('reason')) ?>" placeholder="Producto defectuoso, error comercial, cliente arrepentido...">
            </div>

            <div class="col-12">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Vendida</th>
                                <th>Devuelta</th>
                                <th>Disponible</th>
                                <th>Cantidad a devolver</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saleItems as $item): ?>
                                <?php $available = max(0, (float) $item['quantity'] - (float) ($item['returned_quantity'] ?? 0)); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc($item['product_name']) ?></div>
                                        <div class="small text-secondary"><?= esc($item['sku']) ?></div>
                                    </td>
                                    <td><?= number_format((float) $item['quantity'], 2, ',', '.') ?></td>
                                    <td><?= number_format((float) ($item['returned_quantity'] ?? 0), 2, ',', '.') ?></td>
                                    <td><?= number_format($available, 2, ',', '.') ?></td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="<?= esc((string) $available) ?>" name="return_items[<?= esc($item['id']) ?>][quantity]" class="form-control" value="<?= esc(old('return_items.' . $item['id'] . '.quantity', '0')) ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="return_items[<?= esc($item['id']) ?>][reason]" class="form-control" value="<?= esc(old('return_items.' . $item['id'] . '.reason')) ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
