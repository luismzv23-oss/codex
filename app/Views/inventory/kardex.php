<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Kardex</h1>
        <p class="text-secondary mb-0">Consulta resumida por producto y detalle operativo de movimientos.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('inventario/kardex') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('inventario/kardex/pdf' . ($selectedCompanyId ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        <?php if ($context['canConfigure']): ?>
            <a href="<?= site_url('inventario/configuracion' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Configuracion</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('inventario/kardex') ?>" class="row g-3 align-items-end">
            <?php if (! empty($companies)): ?>
                <div class="col-md-3">
                    <label class="form-label">Empresa</label>
                    <select name="company_id" class="form-select">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Fecha desde</label>
                <input type="date" name="start_date" class="form-control" value="<?= esc($filters['start_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha hasta</label>
                <input type="date" name="end_date" class="form-control" value="<?= esc($filters['end_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Producto</label>
                <select name="product_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= esc($product['id']) ?>" <?= ($filters['product_id'] ?? '') === $product['id'] ? 'selected' : '' ?>><?= esc($product['sku'] . ' - ' . $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Origen</label>
                <select name="source_warehouse_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>" <?= ($filters['source_warehouse_id'] ?? '') === $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Movimiento</label>
                <select name="movement_type" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['ingreso' => 'Ingreso', 'egreso' => 'Egreso', 'transferencia' => 'Transferencia', 'ajuste' => 'Ajuste'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['movement_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Destino</label>
                <select name="destination_warehouse_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= esc($warehouse['id']) ?>" <?= ($filters['destination_warehouse_id'] ?? '') === $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Documento</label>
                <input type="text" name="source_document" class="form-control" value="<?= esc($filters['source_document'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Motivo</label>
                <input type="text" name="reason" class="form-control" value="<?= esc($filters['reason'] ?? '') ?>">
            </div>
            <div class="col-md-2"><button class="btn btn-dark w-100">Filtrar</button></div>
            <div class="col-md-2"><a href="<?= site_url('inventario/kardex' . ($selectedCompanyId ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark w-100">Limpiar</a></div>
            <div class="col-md-2"><a href="<?= site_url('inventario/kardex/pdf?' . http_build_query(array_filter(['company_id' => $selectedCompanyId] + $filters, static fn($value) => $value !== ''))) ?>" class="btn btn-outline-dark w-100"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1">Resumen por producto</h2>
                <p class="text-secondary mb-0">Cada producto aparece una sola vez con acceso a detalle y trazabilidad.</p>
            </div>
            <div class="small text-secondary">Metodo de costeo: <?=
                esc(match ($settings['valuation_method'] ?? 'weighted_average') {
                    'fifo' => 'FIFO',
                    'lifo' => 'LIFO',
                    default => 'Promedio ponderado',
                })
            ?></div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" data-codex-pagination="8">
                <thead><tr><th>Producto</th><th>Unidad</th><th>Movimientos</th><th>Entradas</th><th>Salidas</th><th>Transferencias</th><th>Stock</th><th>Valorizacion</th><th>Ultimo movimiento</th></tr></thead>
                <tbody>
                    <?php foreach ($summaryRows as $row): ?>
                        <?php $detailQuery = http_build_query(array_filter(['company_id' => $selectedCompanyId] + $filters, static fn($value) => $value !== '')); ?>
                        <tr>
                            <td>
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <strong><?= esc($row['sku'] . ' - ' . $row['product_name']) ?></strong>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <a href="<?= site_url('inventario/kardex/productos/' . $row['product_id'] . '/detalle?' . $detailQuery) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Detalle de kardex" data-popup-subtitle="Movimientos filtrados del producto." title="Detalle del producto" aria-label="Detalle del producto"><i class="bi bi-list-ul"></i></a>
                                        <a href="<?= site_url('inventario/productos/' . $row['product_id'] . '/trazabilidad?' . $detailQuery) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Trazabilidad del producto" aria-label="Trazabilidad del producto"><i class="bi bi-diagram-3"></i></a>
                                        <a href="<?= site_url('inventario/productos/' . $row['product_id'] . '/pdf?' . $detailQuery) ?>" class="btn btn-sm btn-outline-danger icon-btn" title="PDF del producto" aria-label="PDF del producto" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
                                    </div>
                                </div>
                            </td>
                            <td><?= esc($row['unit']) ?></td>
                            <td><?= esc((string) $row['movement_count']) ?></td>
                            <td><?= number_format((float) $row['total_in'], 2, ',', '.') ?></td>
                            <td><?= number_format((float) $row['total_out'], 2, ',', '.') ?></td>
                            <td><?= number_format((float) $row['total_transfer'], 2, ',', '.') ?></td>
                            <td><?= number_format((float) $row['current_stock'], 2, ',', '.') ?><div class="small text-secondary">Disponible: <?= number_format((float) $row['available_stock'], 2, ',', '.') ?></div></td>
                            <td><?= number_format((float) $row['stock_value'], 2, ',', '.') ?><div class="small text-secondary">Costo prom.: <?= number_format((float) $row['average_cost'], 4, ',', '.') ?></div></td>
                            <td><?= $row['last_movement_at'] ? esc(date('d/m/Y H:i', strtotime($row['last_movement_at']))) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($summaryRows === []): ?><tr><td colspan="9" class="text-secondary">No hay productos para los filtros seleccionados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1">Movimientos filtrados</h2>
                <p class="text-secondary mb-0">Incluye documento, costo, lote, serie y vencimiento para auditoria operativa.</p>
            </div>
            <div class="small text-secondary">Registros: <?= esc((string) count($rows)) ?></div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" data-codex-pagination="10">
                <thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Documento</th><th>Costo</th><th>Lote / Serie</th><th>Responsable</th><th>Motivo</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                            <td><?= esc($row['sku'] . ' - ' . $row['product_name']) ?></td>
                            <td><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' - ' . esc($row['adjustment_mode']) : '' ?></td>
                            <td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                            <td><?= esc($row['source_name'] ?: '-') ?></td>
                            <td><?= esc($row['destination_name'] ?: '-') ?></td>
                            <td><?= esc($row['source_document'] ?: '-') ?></td>
                            <td><?php if ($row['unit_cost'] !== null): ?><?= number_format((float) $row['unit_cost'], 4, ',', '.') ?><div class="small text-secondary">Total: <?= number_format((float) ($row['total_cost'] ?? 0), 4, ',', '.') ?></div><?php else: ?>-<?php endif; ?></td>
                            <td><?= esc($row['lot_number'] ?: '-') ?><div class="small text-secondary"><?= esc($row['serial_number'] ?: '-') ?><?= ! empty($row['expiration_date']) ? ' / ' . esc($row['expiration_date']) : '' ?></div></td>
                            <td><?= esc($row['user_name']) ?></td>
                            <td><?= esc($row['reason'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="11" class="text-secondary">No hay movimientos para el filtro seleccionado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
