<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= esc($product['sku'] . ' - ' . $product['name']) ?></h2>
                <p class="text-secondary mb-0">Detalle de movimientos del producto segun los filtros activos de Kardex.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('inventario/kardex/productos/' . $product['id'] . '/movimientos-pdf?' . http_build_query(array_filter(['company_id' => $companyId] + $filters, static fn($value) => $value !== ''))) ?>" class="btn btn-outline-danger icon-btn" title="PDF movimientos" aria-label="PDF movimientos" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
                <a href="<?= site_url('inventario/productos/' . $product['id'] . '/trazabilidad?company_id=' . $companyId) ?>" class="btn btn-outline-secondary icon-btn" title="Trazabilidad" aria-label="Trazabilidad" target="_blank"><i class="bi bi-diagram-3"></i></a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Movimiento</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Documento</th><th>Costo</th><th>Responsable</th><th>Motivo</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                            <td><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' - ' . esc($row['adjustment_mode']) : '' ?></td>
                            <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                            <td><?= esc($row['source_name'] ?: '-') ?></td>
                            <td><?= esc($row['destination_name'] ?: '-') ?></td>
                            <td><?= esc($row['source_document'] ?: '-') ?></td>
                            <td><?php if ($row['unit_cost'] !== null): ?><?= number_format((float) $row['unit_cost'], 4, ',', '.') ?><?php else: ?>-<?php endif; ?></td>
                            <td><?= esc($row['user_name']) ?></td>
                            <td><?= esc($row['reason'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="9" class="text-secondary">No hay movimientos para este producto con los filtros seleccionados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
