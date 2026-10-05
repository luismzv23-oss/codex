<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/kardex-dashboard.css') ?>">
<div class="insight-shell inventory-shell kardex-shell">
<?php $exportQuery = http_build_query(array_filter(['company_id'=>$selectedCompanyId] + $filters, static fn($value)=>$value !== '')); ?>
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / KARDEX</div>
        <h1>Kardex</h1>
        <p>Consulta movimientos, identifica ajustes y sigue la trazabilidad de tus productos.</p>
        <div class="insight-identity"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Control de inventario · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="submit" form="kardex-filters" class="btn btn-outline-dark icon-btn" title="Actualizar Kardex" aria-label="Actualizar Kardex"><i class="bi bi-arrow-clockwise"></i></button>
        <a href="<?= site_url('inventario?company_id='.$selectedCompanyId) ?>" class="btn btn-outline-dark icon-btn" title="Volver a Inventario" aria-label="Volver a Inventario"><i class="bi bi-box-seam"></i></a>
        <a href="<?= site_url('inventario/kardex/pdf?'.$exportQuery) ?>" class="btn btn-outline-dark icon-btn" title="Exportar PDF filtrado" aria-label="Exportar PDF filtrado"><i class="bi bi-file-earmark-pdf"></i></a>
        <?php if ($context['canConfigure']): ?><a href="<?= site_url('inventario/configuracion?company_id='.$selectedCompanyId) ?>" class="btn btn-outline-dark icon-btn" title="Configuración" aria-label="Configuración"><i class="bi bi-gear"></i></a><?php endif; ?>
    </div>
</header>
<form id="kardex-filters" action="<?= site_url('inventario/kardex') ?>" method="get" class="kardex-filter-card">
    <div class="insight-filters">
        <label>Empresa activa<select name="company_id" class="form-select"><?php foreach(($companies ?: [$context['company']]) as $company): ?><option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId===$company['id']?'selected':'' ?>><?= esc($company['name']) ?></option><?php endforeach; ?></select></label>
        <label>Filtrar por producto<select name="product_id" class="form-select"><option value="">Todos los productos</option><?php foreach($products as $product): ?><option value="<?= esc($product['id']) ?>" <?= ($filters['product_id']??'')===$product['id']?'selected':'' ?>><?= esc($product['sku'].' - '.$product['name']) ?></option><?php endforeach; ?></select></label>
        <label>Resumen desde<input type="date" name="start_date" class="form-control" value="<?= esc($filters['start_date']) ?>" required></label>
        <label>Resumen hasta<input type="date" name="end_date" class="form-control" value="<?= esc($filters['end_date']) ?>" required></label>
        <div class="insight-filter-actions"><button class="btn insight-action" title="Aplicar filtros y actualizar" aria-label="Aplicar filtros y actualizar"><i class="bi bi-arrow-repeat"></i></button></div>
    </div>

</form>
<div id="kardex-refresh-status" class="small text-secondary" role="status" aria-live="polite"></div>
<div id="kardex-dashboard-panel"><?= view('inventory/kardex_dashboard',['dashboard'=>$dashboard]) ?></div>
<div class="insight-section-label"><span>DETALLE POR PRODUCTO</span><span>Stock y valorización actuales de toda la empresa</span></div>
<div class="insight-panel mb-4" id="kardex-products">
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
        <div class="table-responsive kardex-table-wrap">
            <table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Producto</th><th>Unidad</th><th>Movimientos</th><th>Entradas</th><th>Salidas</th><th>Transferencias</th><th>Stock</th><th>Valorizacion</th><th>Ultimo movimiento</th></tr></thead>
                <tbody>
                    <?php foreach ($summaryRows as $row): ?>
                        <?php $detailQuery = http_build_query(array_filter(['company_id' => $selectedCompanyId] + $filters, static fn($value) => $value !== '')); ?>
                        <tr>
                            <td data-label="Producto">
                                <div class="d-flex flex-column align-items-start gap-2">
                                    <div>
                                        <strong><?= esc($row['sku'] . ' - ' . $row['product_name']) ?></strong>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="<?= site_url('inventario/kardex/productos/' . $row['product_id'] . '/detalle?' . $detailQuery) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Detalle de kardex" data-popup-subtitle="Movimientos filtrados del producto." title="Detalle del producto" aria-label="Detalle del producto"><i class="bi bi-list-ul"></i></a>
                                        <a href="<?= site_url('inventario/productos/' . $row['product_id'] . '/trazabilidad?' . $detailQuery) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Trazabilidad del producto" data-popup-subtitle="Historial, stock por deposito y responsables." title="Trazabilidad del producto" aria-label="Trazabilidad del producto"><i class="bi bi-diagram-3"></i></a>
                                        <a href="<?= site_url('inventario/productos/' . $row['product_id'] . '/pdf?' . $detailQuery) ?>" class="btn btn-sm btn-outline-danger icon-btn" title="PDF del producto" aria-label="PDF del producto" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Unidad"><?= esc($row['unit']) ?></td>
                            <td data-label="Movimientos"><?= esc((string) $row['movement_count']) ?></td>
                            <td data-label="Entradas"><?= number_format((float) $row['total_in'], 2, ',', '.') ?></td>
                            <td data-label="Salidas"><?= number_format((float) $row['total_out'], 2, ',', '.') ?></td>
                            <td data-label="Transferencias"><?= number_format((float) $row['total_transfer'], 2, ',', '.') ?></td>
                            <td data-label="Stock"><?= number_format((float) $row['current_stock'], 2, ',', '.') ?><div class="small text-secondary">Disponible: <?= number_format((float) $row['available_stock'], 2, ',', '.') ?></div></td>
                            <td data-label="Valorización"><?= number_format((float) $row['stock_value'], 2, ',', '.') ?><div class="small text-secondary">Costo prom.: <?= number_format((float) $row['average_cost'], 4, ',', '.') ?></div></td>
                            <td data-label="Último movimiento"><?= $row['last_movement_at'] ? esc(date('d/m/Y H:i', strtotime($row['last_movement_at']))) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($summaryRows === []): ?><tr><td colspan="9" class="text-secondary">No hay productos para los filtros seleccionados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="insight-panel" id="kardex-movements">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1">Movimientos filtrados</h2>
                <p class="text-secondary mb-0">Incluye documento, costo, lote, serie y vencimiento para auditoria operativa.</p>
            </div>
            <div class="small text-secondary">Mostrando <?= count($rows) ?> de <?= $dashboard['total'] ?> movimientos (hasta 400 recientes)</div>
        </div>
        <div class="table-responsive kardex-table-wrap">
            <table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Cantidad</th><th>Origen</th><th>Destino</th><th>Documento</th><th>Costo</th><th>Lote / Serie</th><th>Responsable</th><th>Motivo</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td data-label="Fecha"><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td>
                            <td data-label="Producto"><?= esc($row['sku'] . ' - ' . $row['product_name']) ?></td>
                            <td data-label="Movimiento"><?= esc(ucfirst($row['movement_type'])) ?><?= ! empty($row['adjustment_mode']) ? ' - ' . esc($row['adjustment_mode']) : '' ?></td>
                            <td data-label="Cantidad"><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td>
                            <td data-label="Origen"><?= esc($row['source_name'] ?: '-') ?></td>
                            <td data-label="Destino"><?= esc($row['destination_name'] ?: '-') ?></td>
                            <td data-label="Documento"><?= esc($row['source_document'] ?: '-') ?></td>
                            <td data-label="Costo"><?php if ($row['unit_cost'] !== null): ?><?= number_format((float) $row['unit_cost'], 4, ',', '.') ?><div class="small text-secondary">Total: <?= number_format((float) ($row['total_cost'] ?? 0), 4, ',', '.') ?></div><?php else: ?>-<?php endif; ?></td>
                            <td data-label="Lote / Serie"><?= esc($row['lot_number'] ?: '-') ?><div class="small text-secondary"><?= esc($row['serial_number'] ?: '-') ?><?= ! empty($row['expiration_date']) ? ' / ' . esc($row['expiration_date']) : '' ?></div></td>
                            <td data-label="Responsable"><?= esc($row['user_name']) ?></td>
                            <td data-label="Motivo"><?= esc($row['reason'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="11" class="text-secondary">No hay movimientos para el filtro seleccionado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<script src="<?= base_url('assets/js/kardex-dashboard.js') ?>" defer></script>
<?= $this->endSection() ?>
