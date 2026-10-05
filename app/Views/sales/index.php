<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $salesLive = true; $isVendedorAccess = ($user['role_slug'] ?? '') === 'vendedor' || ($context['access_level'] ?? '') === 'vendedor'; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
<header class="insight-hero sales-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / VENTAS</div><h1 class="h2 mb-1">Ventas</h1>
        <p class="text-secondary mb-0">Ventas, clientes, pagos y devoluciones integradas con inventario.</p><div class="insight-identity"><i class="bi bi-bag-check" aria-hidden="true"></i><span>Gestión comercial · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <?= view('sales/banner_actions', get_defined_vars()) ?>
</header>
<?php endif; ?>
<?php if (!$isVendedorAccess): ?>
<form method="get" action="<?= site_url('ventas') ?>" class="insight-filters sales-filters">
            <div class="sales-company-filter"><label class="form-label">Empresa activa</label><select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $selectedCompanyId === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3">
                <label class="form-label">Estado fiscal</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['draft' => 'Borrador', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'returned_partial' => 'Devuelta parcial', 'returned_total' => 'Devuelta total'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <select name="customer_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= esc($customer['id']) ?>" <?= ($filters['customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= esc($customer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="date_from" class="form-control"
                    value="<?= esc($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-1"><button class="btn btn-dark w-100" title="Aplicar filtros" aria-label="Aplicar filtros"><i class="bi bi-arrow-repeat"></i></button></div>
            <div class="col-md-1"><a
                    href="<?= site_url('ventas' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                    class="btn btn-outline-dark w-100" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a></div>
        </form>
<?php endif; ?>
<div id="sales-status" role="status" aria-live="polite" class="small text-secondary mb-2"></div>
<div id="sales-content">

<?php if ($isVendedorAccess): ?>
    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
        <div class="card-body">
            <div class="display-1 text-secondary mb-3"><i class="bi bi-shop-window"></i></div>
            <h2 class="h4">Bienvenido al Portal de Ventas</h2>
            <p class="text-secondary mx-auto mb-4" style="max-width: 500px;">
                Selecciona <strong>POS</strong> para registrar ventas tradicionales con facturación y métodos de pago complejos, o selecciona <strong>Kiosco</strong> para ventas de mostrador de alta velocidad.
            </p>
            <div class="sales-portal-actions d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= site_url('ventas/pos' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-primary btn-lg px-4"><i class="bi bi-display me-2"></i> Entrar a POS</a>
                <a href="<?= site_url('ventas/kiosco' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark btn-lg px-4"><i class="bi bi-shop me-2"></i> Entrar a Kiosco</a>
            </div>
        </div>
    </div>
<?php else: ?>

<?= view('sales/overview', get_defined_vars()) ?>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Listas de precio activas</h2>
                        <p class="text-secondary mb-0">Precios comerciales listos para ventas y POS.</p>
                    </div>
                    <span class="badge text-bg-dark"><?= count($priceLists) ?></span>
                </div>
                <?php foreach ($priceLists as $priceList): ?>
                    <div class="border rounded-3 p-3 mb-2" data-sales-item>
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <strong><?= esc($priceList['name']) ?></strong>
                                <div class="small text-secondary"><?= esc($priceList['description'] ?: 'Sin descripcion') ?>
                                </div>
                            </div>
                            <span
                                class="small <?= (int) ($priceList['is_default'] ?? 0) === 1 ? 'text-success' : 'text-secondary' ?>"><?= (int) ($priceList['is_default'] ?? 0) === 1 ? 'Base' : 'Activa' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($priceLists === []): ?>
                    <div class="text-secondary">No hay listas de precio creadas.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h4 mb-1">Promociones vigentes</h2>
                        <p class="text-secondary mb-0">Descuentos automáticos aplicables en ventas.</p>
                    </div>
                    <span class="badge text-bg-dark"><?= count($promotions) ?></span>
                </div>
                <?php foreach ($promotions as $promotion): ?>
                    <div class="border rounded-3 p-3 mb-2" data-sales-item>
                        <strong><?= esc($promotion['name']) ?></strong>
                        <div class="small text-secondary">
                            <?= esc($promotion['promotion_type'] === 'percent' ? number_format((float) $promotion['value'], 2, ',', '.') . '% off' : 'Descuento fijo ' . number_format((float) $promotion['value'], 2, ',', '.')) ?>
                            / <?= esc($promotion['scope'] === 'all' ? 'Todos los productos' : 'Productos seleccionados') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($promotions === []): ?>
                    <div class="text-secondary">No hay promociones activas.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>



<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="h4 mb-0">Comprobantes ARCA</h2>
                <p class="text-secondary small mb-0">Solo facturas y tickets presentados o autorizados ante ARCA/AFIP.
                </p>
            </div>
            <!--<a href="<?= site_url('ventas/diarios' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                class="btn btn-outline-dark btn-sm">Ver todos los comprobantes →</a>-->
        </div>
        <div class="table-responsive">
            <table data-sales-table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Comprobante</th>
                        <th>Cliente</th>
                        <th>CAE</th>
                        <th>Estado ARCA</th>
                        <th>Servicio</th>
                        <th>Pago</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>
                                <?= esc(($sale['document_type_name'] ?? 'Venta') . ' ' . $sale['sale_number']) ?>
                                <div class="small text-secondary"><?= esc($sale['created_by_name'] ?: '-') ?></div>
                            </td>
                            <td><?= esc($sale['customer_name'] ?: ($sale['customer_name_snapshot'] ?? 'Consumidor Final')) ?>
                            </td>
                            <td>
                                <?php if (!empty($sale['cae'])): ?>
                                    <span
                                        class="badge bg-success-subtle text-success-emphasis border border-success-subtle font-monospace"><?= esc($sale['cae']) ?></span>
                                    <?php if (!empty($sale['cae_due_date'])): ?>
                                        <div class="small text-secondary">Vence:
                                            <?= esc(date('d/m/Y', strtotime($sale['cae_due_date']))) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $arcaBadge = match ($sale['arca_status'] ?? '') {
                                    'Autorizado', 'Ok' => 'success',
                                    'pending' => 'warning',
                                    'error' => 'danger',
                                    default => 'secondary',
                                };
                                ?>
                                <span
                                    class="badge text-bg-<?= $arcaBadge ?> rounded-pill"><?= esc($sale['arca_status'] ?: 'Sin estado') ?></span>
                            </td>
                            <td><span
                                    class="small text-secondary"><?= esc(strtoupper((string) ($sale['arca_service'] ?? '-'))) ?></span>
                            </td>
                            <td><?= esc(match ($sale['payment_status']) {
                                'paid' => 'Pagado',
                                'partial' => 'Parcial',
                                default => 'Pendiente',
                            }) ?></td>
                            <td><?= number_format((float) $sale['total'], 2, ',', '.') ?></td>
                            <td><?= esc(date('d/m/Y H:i', strtotime($sale['issue_date']))) ?></td>
                            <td class="text-end">
                                <a href="<?= site_url('ventas/' . $sale['id'] . '/pdf' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                    class="btn btn-sm btn-outline-danger icon-btn" title="PDF" aria-label="PDF"
                                    target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
                                <?php if ($context['canManage'] && in_array(($sale['document_category'] ?? ''), ['invoice', 'ticket', 'credit_note', 'debit_note'], true) && in_array($sale['status'], ['confirmed', 'returned_partial', 'returned_total'], true) && empty($sale['cae'])): ?>
                                    <form method="post"
                                        action="<?= site_url('ventas/' . $sale['id'] . '/arca/autorizar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                        class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-primary icon-btn" title="Autorizar ARCA"
                                            aria-label="Autorizar ARCA"><i class="bi bi-shield-check"></i></button>
                                    </form>
                                    <form method="post"
                                        action="<?= site_url('ventas/' . $sale['id'] . '/arca/consultar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>"
                                        class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-dark icon-btn" title="Consultar ARCA"
                                            aria-label="Consultar ARCA"><i class="bi bi-search"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($sales === []): ?>
                        <tr>
                            <td colspan="9" class="text-secondary">No hay comprobantes presentados o autorizados ante ARCA.
                            </td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
</div>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
