<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-12">
        <div class="p-4 rounded-4 bg-white shadow-sm border">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <p class="small text-uppercase text-secondary mb-2">ERP</p>
                    <h1 class="h2 mb-2">Bienvenido, <?= esc($user['name'] ?? '') ?></h1>
                    <p class="text-secondary mb-0">Rol activo: <?= esc($user['role_name'] ?? '') ?><?= ! empty($user['company_name']) ? ' | Empresa: ' . esc($user['company_name']) : '' ?></p>
                </div>
                <div class="text-end">
                    <div class="small text-secondary">Readiness</div>
                    <div class="d-flex align-items-center gap-2 justify-content-end">
                        <span class="badge text-bg-<?= ($readiness['status'] ?? 'blocked') === 'ready' ? 'success' : (($readiness['status'] ?? 'blocked') === 'warning' ? 'warning' : 'danger') ?>"><?= esc(strtoupper($readiness['status'] ?? 'blocked')) ?></span>
                        <span class="fw-semibold"><?= esc((string) ($readiness['score'] ?? 0)) ?>%</span>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-2">
                        <a href="<?= site_url('dashboard/readiness') ?>" class="btn btn-outline-dark btn-sm">Diagnostico ERP</a>
                        <a href="<?= site_url('dashboard/qa') ?>" class="btn btn-outline-dark btn-sm">QA integral</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Empresas</div><div class="display-6 fw-bold"><?= esc((string) $stats['companies']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Usuarios</div><div class="display-6 fw-bold"><?= esc((string) $stats['users']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Sucursales</div><div class="display-6 fw-bold"><?= esc((string) $stats['branches']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Clientes</div><div class="display-6 fw-bold"><?= esc((string) ($stats['customers'] ?? 0)) ?></div></div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Ventas acumuladas</div><div class="display-6 fw-bold"><?= number_format((float) ($stats['sales_total'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Ventas de hoy</div><div class="display-6 fw-bold"><?= number_format((float) ($stats['sales_today'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Compras acumuladas</div><div class="display-6 fw-bold"><?= number_format((float) ($stats['purchase_total'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Margen comercial</div><div class="display-6 fw-bold text-success"><?= number_format((float) ($stats['sales_margin'] ?? 0), 2, ',', '.') ?></div></div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Saldo por cobrar</div><div class="display-6 fw-bold"><?= number_format((float) ($stats['receivable_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Saldo por pagar</div><div class="display-6 fw-bold"><?= number_format((float) ($stats['payable_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Cajas abiertas</div><div class="display-6 fw-bold"><?= esc((string) ($stats['open_cash_sessions'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-secondary small">Stock critico</div><div class="display-6 fw-bold text-danger"><?= esc((string) ($stats['critical_stock'] ?? 0)) ?></div></div></div></div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Salud operativa</h2>
                <?php foreach (($alerts ?? []) as $alert): ?>
                    <div class="border rounded-4 p-3 mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= esc($alert['label']) ?></div>
                            <div class="small text-secondary">Estado ejecutivo del ERP</div>
                        </div>
                        <span class="badge text-bg-<?= esc($alert['tone']) ?>"><?= esc((string) $alert['value']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Rendimiento comercial</h2>
                <p class="text-secondary mb-4">Ventas y margen de los ultimos seis meses.</p>
                <?php $maxSeries = max(array_map(static fn(array $row): float => max((float) ($row['amount'] ?? 0), 1), $marketingSeries ?: [['amount' => 1]])); ?>
                <?php foreach (($marketingSeries ?? []) as $row): ?>
                    <?php $salesWidth = $maxSeries > 0 ? max(6, (int) round(((float) $row['amount'] / $maxSeries) * 100)) : 6; ?>
                    <?php $marginWidth = $maxSeries > 0 ? max(4, (int) round(((float) ($row['margin'] ?? 0) / $maxSeries) * 100)) : 4; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= esc($row['label']) ?></span>
                            <strong>Ventas <?= number_format((float) $row['amount'], 2, ',', '.') ?> | Margen <?= number_format((float) ($row['margin'] ?? 0), 2, ',', '.') ?></strong>
                        </div>
                        <div class="progress mb-1" style="height: 10px;"><div class="progress-bar bg-dark" style="width: <?= esc((string) $salesWidth) ?>%"></div></div>
                        <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: <?= esc((string) $marginWidth) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Operacion por sucursal</h2>
                <p class="text-secondary mb-4">Volumen comercial y margen por unidad operativa.</p>
                <?php foreach (($branchPerformance ?? []) as $branch): ?>
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold"><?= esc($branch['branch_name']) ?></div>
                                <div class="small text-secondary"><?= esc((string) ($branch['sales_count'] ?? 0)) ?> ventas registradas</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold"><?= number_format((float) ($branch['total_amount'] ?? 0), 2, ',', '.') ?></div>
                                <div class="small text-secondary">Margen <?= number_format((float) ($branch['margin_total'] ?? 0), 2, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (($branchPerformance ?? []) === []): ?><div class="text-secondary">Todavia no hay datos operativos para mostrar.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Actividad reciente</h2>
                <p class="text-secondary mb-4">Auditoria funcional e integraciones del ERP.</p>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Auditoria de hoy</strong>
                                <span class="badge text-bg-dark"><?= esc((string) ($stats['audit_today'] ?? 0)) ?></span>
                            </div>
                            <?php foreach (($recentAudit ?? []) as $row): ?>
                                <div class="small mb-2"><strong><?= esc($row['action']) ?></strong> en <?= esc($row['entity_type']) ?> por <?= esc($row['user_name'] ?? '-') ?></div>
                            <?php endforeach; ?>
                            <?php if (($recentAudit ?? []) === []): ?><div class="small text-secondary">Sin eventos recientes.</div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Integraciones</strong>
                                <span class="badge text-bg-<?= (($stats['integration_errors'] ?? 0) > 0) ? 'danger' : 'success' ?>"><?= esc((string) ($stats['integration_errors'] ?? 0)) ?> errores</span>
                            </div>
                            <?php foreach (($recentIntegrations ?? []) as $row): ?>
                                <div class="small mb-2"><strong><?= esc($row['provider']) ?>/<?= esc($row['service']) ?></strong> - <?= esc($row['status']) ?><?= ! empty($row['message']) ? ' | ' . esc($row['message']) : '' ?></div>
                            <?php endforeach; ?>
                            <?php if (($recentIntegrations ?? []) === []): ?><div class="small text-secondary">Sin integraciones recientes.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
