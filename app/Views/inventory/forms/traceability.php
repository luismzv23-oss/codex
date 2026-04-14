<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
    .traceability-timeline {
        position: relative;
        padding-left: 1.4rem;
    }
    .traceability-timeline::before {
        content: '';
        position: absolute;
        left: 0.45rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(180deg, rgba(202, 90, 46, 0.5), rgba(48, 127, 109, 0.25));
    }
    .traceability-node {
        position: relative;
        padding: 0.9rem 1rem 0.9rem 1.2rem;
        border: 1px solid rgba(28, 28, 28, 0.08);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.78);
        margin-bottom: 0.9rem;
    }
    .traceability-node::before {
        content: '';
        position: absolute;
        left: -1.2rem;
        top: 1.1rem;
        width: 0.8rem;
        height: 0.8rem;
        border-radius: 999px;
        background: #ca5a2e;
        box-shadow: 0 0 0 0.2rem rgba(202, 90, 46, 0.18);
    }
</style>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= esc($product['sku'] . ' - ' . $product['name']) ?></h2>
                <p class="text-secondary mb-0">Trazabilidad completa por deposito, responsable, fecha, reserva y motivo del movimiento.</p>
            </div>
            <?php if (! empty($companyId)): ?>
                <a href="<?= site_url('inventario/productos/' . $product['id'] . '/trazabilidad-pdf?company_id=' . $companyId) ?>" class="btn btn-outline-danger icon-btn flex-shrink-0" title="PDF de trazabilidad" aria-label="PDF de trazabilidad" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Unidad</div><div class="fw-semibold"><?= esc($product['unit']) ?></div></div></div>
            <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Categoria / Marca</div><div class="fw-semibold"><?= esc(trim(($product['category'] ?? '-') . ' / ' . ($product['brand'] ?? '-'))) ?></div></div></div>
            <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Stock min / max</div><div class="fw-semibold"><?= number_format((float) $product['min_stock'], 2, ',', '.') ?> / <?= number_format((float) ($product['max_stock'] ?? 0), 2, ',', '.') ?></div></div></div>
            <div class="col-md-3"><div class="border rounded-4 p-3"><div class="small text-secondary">Estado / Tipo</div><div class="fw-semibold"><?= (int) $product['active'] === 1 ? 'Activo' : 'Inactivo' ?> / <?= esc($product['product_type'] ?? 'simple') ?></div></div></div>
        </div>

        <h3 class="h6 mb-2">Stock por deposito</h3>
        <div class="table-responsive mb-4">
            <table class="table align-middle mb-0">
                <thead><tr><th>Deposito</th><th>Tipo</th><th>Stock</th><th>Minimo</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($stockByWarehouse as $row): ?>
                        <tr>
                            <td><?= esc($row['name']) ?> <span class="small text-secondary"><?= esc($row['code']) ?></span></td>
                            <td><?= esc($row['type']) ?></td>
                            <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?><div class="small text-secondary">Reservado: <?= number_format((float) ($row['reserved_quantity'] ?? 0), 2, ',', '.') ?> / Disponible: <?= number_format((float) ($row['available_quantity'] ?? 0), 2, ',', '.') ?></div></td>
                            <td><?= number_format((float) $row['min_stock'], 2, ',', '.') ?></td>
                            <td><?= (int) $row['active'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="h6 mb-2">Stock por ubicacion interna</h3>
        <div class="table-responsive mb-4">
            <table class="table align-middle mb-0">
                <thead><tr><th>Deposito</th><th>Ubicacion</th><th>Zona/Rack/Nivel</th><th>Stock</th><th>Disponible</th></tr></thead>
                <tbody>
                    <?php foreach ($stockByLocation as $row): ?>
                        <tr>
                            <td><?= esc($row['warehouse_name']) ?></td>
                            <td><?= esc($row['name']) ?> <span class="small text-secondary"><?= esc($row['code']) ?></span></td>
                            <td><?= esc(trim(($row['zone'] ?: '-') . ' / ' . ($row['rack'] ?: '-') . ' / ' . ($row['level'] ?: '-'))) ?></td>
                            <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                            <td><?= number_format((float) $row['available_quantity'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($stockByLocation === []): ?><tr><td colspan="5" class="text-secondary">No hay ubicaciones registradas para este producto.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3 class="h6 mb-2">Reservas activas</h3>
        <div class="table-responsive mb-4">
            <table class="table align-middle mb-0">
                <thead><tr><th>Deposito</th><th>Cantidad</th><th>Referencia</th><th>Responsable</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($reservations as $row): ?>
                        <tr>
                            <td><?= esc($row['warehouse_name']) ?> <span class="small text-secondary"><?= esc($row['warehouse_code']) ?></span></td>
                            <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                            <td><?= esc($row['reference'] ?: '-') ?></td>
                            <td><?= esc($row['reserved_by_name']) ?></td>
                            <td><?= esc(date('d/m/Y H:i', strtotime($row['reserved_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($reservations === []): ?><tr><td colspan="5" class="text-secondary">No hay reservas activas para este producto.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <h3 class="h6 mb-2">Lotes</h3>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Lote</th><th>Deposito/Ubicacion</th><th>Vence</th><th>Saldo</th></tr></thead>
                        <tbody>
                            <?php foreach ($lots as $row): ?>
                                <tr>
                                    <td><?= esc($row['lot_number']) ?></td>
                                    <td><?= esc($row['warehouse_name']) ?><div class="small text-secondary"><?= esc($row['location_name'] ?: 'Sin ubicacion') ?></div></td>
                                    <td><?= esc($row['expiration_date'] ?: '-') ?></td>
                                    <td><?= number_format((float) $row['quantity_balance'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($lots === []): ?><tr><td colspan="4" class="text-secondary">No hay lotes registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <h3 class="h6 mb-2">Series</h3>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Serie</th><th>Deposito/Ubicacion</th><th>Estado</th><th>Lote</th></tr></thead>
                        <tbody>
                            <?php foreach ($serials as $row): ?>
                                <tr>
                                    <td><?= esc($row['serial_number']) ?></td>
                                    <td><?= esc($row['warehouse_name'] ?: '-') ?><div class="small text-secondary"><?= esc($row['location_name'] ?: 'Sin ubicacion') ?></div></td>
                                    <td><?= esc($row['status']) ?></td>
                                    <td><?= esc($row['lot_number'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($serials === []): ?><tr><td colspan="4" class="text-secondary">No hay series registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($kitItems !== []): ?>
            <h3 class="h6 mb-2">Composicion del kit</h3>
            <div class="table-responsive mb-4">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Componente</th><th>Cantidad</th></tr></thead>
                    <tbody>
                        <?php foreach ($kitItems as $row): ?>
                            <tr>
                                <td><?= esc($row['component_sku'] . ' - ' . $row['component_name']) ?></td>
                                <td><?= number_format((float) $row['quantity'], 4, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="h6 mb-2">Capas de costo</h3>
        <div class="table-responsive mb-4">
            <table class="table align-middle mb-0">
                <thead><tr><th>Tipo</th><th>Deposito/Ubicacion</th><th>Cantidad</th><th>Costo</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($costLayers as $row): ?>
                        <tr>
                            <td><?= esc($row['layer_type']) ?></td>
                            <td><?= esc($row['warehouse_name'] ?: '-') ?><div class="small text-secondary"><?= esc($row['location_name'] ?: 'Sin ubicacion') ?></div></td>
                            <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?><div class="small text-secondary">Restante: <?= number_format((float) $row['remaining_quantity'], 2, ',', '.') ?></div></td>
                            <td><?= number_format((float) $row['unit_cost'], 4, ',', '.') ?><div class="small text-secondary">Total: <?= number_format((float) $row['total_cost'], 2, ',', '.') ?></div></td>
                            <td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($costLayers === []): ?><tr><td colspan="5" class="text-secondary">Todavia no hay capas de costo registradas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3 class="h6 mb-2">Linea de trazabilidad</h3>
        <div class="traceability-timeline">
            <?php foreach ($movements as $row): ?>
                <div class="traceability-node">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <strong><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></strong>
                        <span class="small text-secondary"><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' / ' . esc($row['adjustment_mode']) : '' ?></span>
                    </div>
                    <div class="small mb-1">Cantidad: <?= number_format((float) $row['quantity'], 2, ',', '.') ?><?php if ($row['unit_cost'] !== null): ?> / Costo unitario: <?= number_format((float) $row['unit_cost'], 4, ',', '.') ?> / Total: <?= number_format((float) ($row['total_cost'] ?? 0), 4, ',', '.') ?><?php endif; ?></div>
                    <div class="small text-secondary mb-1">Origen: <?= esc($row['source_name'] ?: '-') ?><?= ! empty($row['source_location_name']) ? ' / ' . esc($row['source_location_name']) : '' ?> / Destino: <?= esc($row['destination_name'] ?: '-') ?><?= ! empty($row['destination_location_name']) ? ' / ' . esc($row['destination_location_name']) : '' ?></div>
                    <div class="small text-secondary mb-1">Responsable: <?= esc($row['user_name']) ?><?= ! empty($row['reason']) ? ' / ' . esc($row['reason']) : '' ?></div>
                    <div class="small text-secondary">Documento: <?= esc($row['source_document'] ?: '-') ?> / Lote: <?= esc($row['lot_number'] ?: '-') ?> / Serie: <?= esc($row['serial_number'] ?: '-') ?> / Vence: <?= esc($row['expiration_date'] ?: '-') ?></div>
                </div>
            <?php endforeach; ?>
            <?php if ($movements === []): ?><div class="text-secondary">Todavia no hay movimientos para este producto.</div><?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
