<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $salesLive = true; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
<header class="insight-hero sales-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / VENTAS</div><h1 class="h2 mb-1">Reportes de ventas</h1>
        <p class="text-secondary mb-0">Indicadores comerciales, top productos, clientes y trazabilidad con inventario.</p><div class="insight-identity"><i class="bi bi-bag-check" aria-hidden="true"></i><span>Gestión comercial · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <div class="sales-hero-actions"><button type="button" class="btn btn-outline-dark icon-btn" data-sales-refresh title="Actualizar resumen" aria-label="Actualizar resumen"><i class="bi bi-arrow-clockwise"></i></button>
        <a href="<?= site_url('ventas/reportes/csv' . (! empty($companies) ? '?company_id=' . $selectedCompanyId . '&date_from=' . ($filters['date_from'] ?? '') . '&date_to=' . ($filters['date_to'] ?? '') : '')) ?>" class="btn btn-outline-success icon-btn" title="Exportar CSV" aria-label="Exportar CSV"><i class="bi bi-filetype-csv"></i></a>
        <a href="<?= site_url('ventas/reportes/pdf' . (! empty($companies) ? '?company_id=' . $selectedCompanyId . '&date_from=' . ($filters['date_from'] ?? '') . '&date_to=' . ($filters['date_to'] ?? '') : '')) ?>" class="btn btn-outline-danger icon-btn" target="_blank" title="Exportar PDF" aria-label="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
        <a href="<?= site_url('ventas' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Volver a ventas" aria-label="Volver a ventas"><i class="bi bi-arrow-left"></i></a>
    </div>
</header>
<?php endif; ?>
<form method="get" action="<?= site_url('ventas/reportes') ?>" class="insight-filters sales-filters">
            <div class="sales-company-filter"><label class="form-label">Empresa activa</label><select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $selectedCompanyId === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Desde</label><input type="date" name="date_from" class="form-control" value="<?= esc($filters['date_from'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label">Hasta</label><input type="date" name="date_to" class="form-control" value="<?= esc($filters['date_to'] ?? '') ?>"></div>
            <div class="col-md-2"><button class="btn btn-dark w-100" title="Aplicar filtros" aria-label="Aplicar filtros"><i class="bi bi-arrow-repeat"></i></button></div>
        </form>
<div id="sales-status" role="status" aria-live="polite" class="small text-secondary mb-2"></div>
<div id="sales-content">



<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Ventas</div><div class="sales-kpi-value fw-semibold"><?= esc((string) $report['summary']['sales_count']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Facturado</div><div class="sales-kpi-value fw-semibold"><?= number_format((float) $report['summary']['gross_total'], 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Cobrado</div><div class="sales-kpi-value fw-semibold"><?= number_format((float) $report['summary']['paid_total'], 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Ticket promedio</div><div class="sales-kpi-value fw-semibold"><?= number_format((float) $report['summary']['average_ticket'], 2, ',', '.') ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Saldo a cobrar</div><div class="sales-kpi-value fw-semibold"><?= number_format((float) ($report['summary']['receivable_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Margen comercial</div><div class="sales-kpi-value fw-semibold text-success"><?= number_format((float) ($report['summary']['margin_total'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-12"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Comisiones proyectadas</div><div class="sales-kpi-value fw-semibold text-primary"><?= number_format((float) ($report['summary']['commission_total'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>

<?= view('sales/activity_chart', get_defined_vars()) ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top productos</h2><?php foreach ($report['top_products'] as $row): ?><div class="border rounded-3 p-3 mb-2" data-sales-item><strong><?= esc($row['product_name']) ?></strong><div class="small text-secondary">Cantidad: <?= number_format((float) $row['qty'], 2, ',', '.') ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top clientes</h2><?php foreach ($report['top_customers'] as $row): ?><div class="border rounded-3 p-3 mb-2" data-sales-item><strong><?= esc($row['customer_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top vendedores</h2><?php foreach (($report['top_agents'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2" data-sales-item><strong><?= esc($row['sales_agent_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?> / Margen: <?= number_format((float) ($row['margin_total'] ?? 0), 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top zonas</h2><?php foreach (($report['top_zones'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2" data-sales-item><strong><?= esc($row['sales_zone_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Canales</h2><?php foreach (($report['channel_mix'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2" data-sales-item><strong><?= esc($row['channel_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top comisiones</h2><?php foreach (($report['top_commissions'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2" data-sales-item><strong><?= esc($row['sales_agent_name']) ?></strong><div class="small text-secondary">Operaciones: <?= esc((string) $row['items_count']) ?> / Comision: <?= number_format((float) $row['commission_total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Serie diaria</h2><div class="table-responsive"><table data-sales-table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Ventas</th><th>Total</th><th>Margen</th></tr></thead><tbody><?php foreach (($report['daily_series'] ?? []) as $row): ?><tr><td><?= esc(date('d/m/Y', strtotime($row['report_date']))) ?></td><td><?= esc((string) $row['orders_count']) ?></td><td><?= number_format((float) $row['total'], 2, ',', '.') ?></td><td><?= number_format((float) ($row['margin_total'] ?? 0), 2, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Comisiones recientes</h2><div class="table-responsive"><table data-sales-table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Venta</th><th>Vendedor</th><th>Base</th><th>%</th><th>Comision</th><th>Estado</th></tr></thead><tbody><?php foreach (($report['commissions'] ?? []) as $row): ?><tr><td><?= esc(! empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-') ?></td><td><?= esc($row['sale_number'] ?? '-') ?></td><td><?= esc($row['sales_agent_name'] ?? 'Sin vendedor') ?></td><td><?= number_format((float) ($row['base_amount'] ?? 0), 2, ',', '.') ?></td><td><?= number_format((float) ($row['rate'] ?? 0), 2, ',', '.') ?></td><td><?= number_format((float) ($row['commission_amount'] ?? 0), 2, ',', '.') ?></td><td><?= esc($row['status'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Movimientos de inventario asociados</h2><div class="table-responsive"><table data-sales-table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Documento</th><th>Producto</th><th>Motivo</th><th>Cantidad</th></tr></thead><tbody><?php foreach ($report['inventory_movements'] as $row): ?><tr><td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td><td><?= esc($row['source_document'] ?: '-') ?></td><td><?= esc($row['product_name']) ?></td><td><?= esc($row['reason'] ?: '-') ?></td><td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Auditoria reciente</h2><div class="table-responsive"><table data-sales-table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Accion</th><th>Entidad</th><th>Usuario</th></tr></thead><tbody><?php foreach (($report['audit_logs'] ?? []) as $row): ?><tr><td><?= esc(! empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-') ?></td><td><?= esc($row['action']) ?></td><td><?= esc($row['entity_type']) ?></td><td><?= esc($row['user_name'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
</div>
</div>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
