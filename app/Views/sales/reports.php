<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Reportes de ventas</h1>
        <p class="text-secondary mb-0">Indicadores comerciales, top productos, clientes y trazabilidad con inventario.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('ventas/reportes/csv' . (! empty($companies) ? '?company_id=' . $selectedCompanyId . '&date_from=' . ($filters['date_from'] ?? '') . '&date_to=' . ($filters['date_to'] ?? '') : '')) ?>" class="btn btn-outline-success">CSV</a>
        <a href="<?= site_url('ventas/reportes/pdf' . (! empty($companies) ? '?company_id=' . $selectedCompanyId . '&date_from=' . ($filters['date_from'] ?? '') . '&date_to=' . ($filters['date_to'] ?? '') : '')) ?>" class="btn btn-outline-danger" target="_blank">PDF</a>
        <a href="<?= site_url('ventas' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Volver</a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('ventas/reportes') ?>" class="row g-3 align-items-end">
            <?php if (! empty($companies)): ?><input type="hidden" name="company_id" value="<?= esc($selectedCompanyId) ?>"><?php endif; ?>
            <div class="col-md-3"><label class="form-label">Desde</label><input type="date" name="date_from" class="form-control" value="<?= esc($filters['date_from'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label">Hasta</label><input type="date" name="date_to" class="form-control" value="<?= esc($filters['date_to'] ?? '') ?>"></div>
            <div class="col-md-2"><button class="btn btn-dark w-100">Filtrar</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Ventas</div><div class="display-6 fw-semibold"><?= esc((string) $report['summary']['sales_count']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Facturado</div><div class="display-6 fw-semibold"><?= number_format((float) $report['summary']['gross_total'], 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Cobrado</div><div class="display-6 fw-semibold"><?= number_format((float) $report['summary']['paid_total'], 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Ticket promedio</div><div class="display-6 fw-semibold"><?= number_format((float) $report['summary']['average_ticket'], 2, ',', '.') ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Saldo a cobrar</div><div class="display-6 fw-semibold"><?= number_format((float) ($report['summary']['receivable_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Margen comercial</div><div class="display-6 fw-semibold text-success"><?= number_format((float) ($report['summary']['margin_total'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-12"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Comisiones proyectadas</div><div class="display-6 fw-semibold text-primary"><?= number_format((float) ($report['summary']['commission_total'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top productos</h2><?php foreach ($report['top_products'] as $row): ?><div class="border rounded-3 p-3 mb-2"><strong><?= esc($row['product_name']) ?></strong><div class="small text-secondary">Cantidad: <?= number_format((float) $row['qty'], 2, ',', '.') ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top clientes</h2><?php foreach ($report['top_customers'] as $row): ?><div class="border rounded-3 p-3 mb-2"><strong><?= esc($row['customer_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top vendedores</h2><?php foreach (($report['top_agents'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2"><strong><?= esc($row['sales_agent_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?> / Margen: <?= number_format((float) ($row['margin_total'] ?? 0), 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top zonas</h2><?php foreach (($report['top_zones'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2"><strong><?= esc($row['sales_zone_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Canales</h2><?php foreach (($report['channel_mix'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2"><strong><?= esc($row['channel_name']) ?></strong><div class="small text-secondary">Ventas: <?= esc((string) $row['orders_count']) ?> / Total: <?= number_format((float) $row['total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h4 mb-3">Top comisiones</h2><?php foreach (($report['top_commissions'] ?? []) as $row): ?><div class="border rounded-3 p-3 mb-2"><strong><?= esc($row['sales_agent_name']) ?></strong><div class="small text-secondary">Operaciones: <?= esc((string) $row['items_count']) ?> / Comision: <?= number_format((float) $row['commission_total'], 2, ',', '.') ?></div></div><?php endforeach; ?></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Serie diaria</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Ventas</th><th>Total</th><th>Margen</th></tr></thead><tbody><?php foreach (($report['daily_series'] ?? []) as $row): ?><tr><td><?= esc(date('d/m/Y', strtotime($row['report_date']))) ?></td><td><?= esc((string) $row['orders_count']) ?></td><td><?= number_format((float) $row['total'], 2, ',', '.') ?></td><td><?= number_format((float) ($row['margin_total'] ?? 0), 2, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Comisiones recientes</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Venta</th><th>Vendedor</th><th>Base</th><th>%</th><th>Comision</th><th>Estado</th></tr></thead><tbody><?php foreach (($report['commissions'] ?? []) as $row): ?><tr><td><?= esc(! empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-') ?></td><td><?= esc($row['sale_number'] ?? '-') ?></td><td><?= esc($row['sales_agent_name'] ?? 'Sin vendedor') ?></td><td><?= number_format((float) ($row['base_amount'] ?? 0), 2, ',', '.') ?></td><td><?= number_format((float) ($row['rate'] ?? 0), 2, ',', '.') ?></td><td><?= number_format((float) ($row['commission_amount'] ?? 0), 2, ',', '.') ?></td><td><?= esc($row['status'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Movimientos de inventario asociados</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Documento</th><th>Producto</th><th>Motivo</th><th>Cantidad</th></tr></thead><tbody><?php foreach ($report['inventory_movements'] as $row): ?><tr><td><?= esc(date('d/m/Y H:i', strtotime($row['occurred_at']))) ?></td><td><?= esc($row['source_document'] ?: '-') ?></td><td><?= esc($row['product_name']) ?></td><td><?= esc($row['reason'] ?: '-') ?></td><td><?= number_format((float) $row['quantity'], 2, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><h2 class="h4 mb-3">Auditoria reciente</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Accion</th><th>Entidad</th><th>Usuario</th></tr></thead><tbody><?php foreach (($report['audit_logs'] ?? []) as $row): ?><tr><td><?= esc(! empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-') ?></td><td><?= esc($row['action']) ?></td><td><?= esc($row['entity_type']) ?></td><td><?= esc($row['user_name'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
</div>
<?= $this->endSection() ?>
