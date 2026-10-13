<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php $salesLive = true; ?>
<?= view('sales/ui_start', get_defined_vars()) ?>
<?php if (!(($isPopup ?? false) || service('request')->getGet('popup') === '1')): ?>
<header class="insight-hero sales-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / VENTAS</div><h1 class="h2 mb-1">Diarios</h1>
        <p class="text-secondary mb-0">Todos los comprobantes, facturas y presupuestos del periodo.</p><div class="insight-identity"><i class="bi bi-bag-check" aria-hidden="true"></i><span>Gestión comercial · <?= esc($context['company']['name']) ?></span></div>
    </div>
    <?= view('sales/banner_actions', get_defined_vars()) ?>
</header>
<?php endif; ?>
<form method="get" action="<?= site_url('ventas/diarios') ?>" class="insight-filters sales-filters">
            <div class="sales-company-filter"><label class="form-label">Empresa activa</label><select name="company_id" class="form-select"><?php foreach (($companies ?: [$context['company']]) as $option): ?><option value="<?= esc($option['id']) ?>" <?= $selectedCompanyId === $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['draft' => 'Borrador', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'returned_partial' => 'Devuelta parcial', 'returned_total' => 'Devuelta total'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
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
                <input type="date" name="date_from" class="form-control" value="<?= esc($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="date_to" class="form-control" value="<?= esc($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-1"><button class="btn btn-dark w-100" title="Aplicar filtros" aria-label="Aplicar filtros"><i class="bi bi-arrow-repeat"></i></button></div>
            <div class="col-md-1"><a href="<?= site_url('ventas/diarios' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark w-100" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a></div>
        </form>
<div id="sales-status" role="status" aria-live="polite" class="small text-secondary mb-2"></div>
<div id="sales-content">

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Borradores</div><div class="sales-kpi-value fw-semibold"><?= esc((string) $summary['drafts']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Confirmadas</div><div class="sales-kpi-value fw-semibold text-success"><?= esc((string) $summary['confirmed']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Devueltas</div><div class="sales-kpi-value fw-semibold text-warning"><?= esc((string) $summary['returned']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Monto total</div><div class="sales-kpi-value fw-semibold"><?= number_format((float) $summary['total_amount'], 2, ',', '.') ?></div></div></div></div>
</div>



<div class="sales-tabs-container mb-3">
    <ul class="nav nav-pills sales-custom-pills" id="diarios-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ventas-tab" data-bs-toggle="pill" data-bs-target="#tab-ventas" type="button" role="tab" aria-controls="tab-ventas" aria-selected="true">
                <i class="bi bi-receipt"></i>
                <span>Comprobantes y Facturación</span>
                <span class="tab-badge"><?= count($sales) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="presupuestos-tab" data-bs-toggle="pill" data-bs-target="#tab-presupuestos" type="button" role="tab" aria-controls="tab-presupuestos" aria-selected="false">
                <i class="bi bi-file-earmark-text"></i>
                <span>Presupuestos</span>
                <span class="tab-badge"><?= count($quotes ?? []) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pedidos-tab" data-bs-toggle="pill" data-bs-target="#tab-pedidos" type="button" role="tab" aria-controls="tab-pedidos" aria-selected="false">
                <i class="bi bi-cart-check"></i>
                <span>Pedidos</span>
                <span class="tab-badge"><?= count($orders ?? []) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="remitos-tab" data-bs-toggle="pill" data-bs-target="#tab-remitos" type="button" role="tab" aria-controls="tab-remitos" aria-selected="false">
                <i class="bi bi-truck"></i>
                <span>Remitos de Entrega</span>
                <span class="tab-badge"><?= count($deliveryNotes ?? []) ?></span>
            </button>
        </li>
    </ul>
</div>

<div class="tab-content" id="diarios-tab-content">
    <div class="tab-pane fade show active" id="tab-ventas" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table data-sales-table class="table align-middle mb-0">
                        <thead><tr><th>Comprobante</th><th>Cliente</th><th>Punto venta</th><th>Deposito</th><th>Estado</th><th>Fiscal</th><th>Pago</th><th>Total</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td>
                                        <?= esc(($sale['document_type_name'] ?? 'Venta') . ' ' . $sale['sale_number']) ?>
                                        <div class="small text-secondary"><?= esc($sale['created_by_name'] ?: '-') ?></div>
                                        <?php if (! empty($sale['source_sale_number'])): ?>
                                            <div class="small text-secondary">Origen: <?= esc(($sale['source_document_name'] ?? $sale['source_document_code'] ?? 'DOC') . ' ' . $sale['source_sale_number']) ?></div>
                                        <?php endif; ?>
                                        <?php if (! empty($sale['cae'])): ?><div class="small text-success">CAE: <?= esc($sale['cae']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= esc($sale['customer_name'] ?: ($sale['customer_name_snapshot'] ?? 'Consumidor Final')) ?></td>
                                    <td><?= esc($sale['point_of_sale_name'] ?: '-') ?></td>
                                    <td><?= esc($sale['warehouse_name'] ?: '-') ?></td>
                                    <td>
                                        <?= esc(match ($sale['status']) {
                                            'draft'            => 'Borrador',
                                            'confirmed'        => 'Confirmada',
                                            'cancelled'        => 'Cancelada',
                                            'returned_partial'  => 'Devuelta parcial',
                                            'returned_total'   => 'Devuelta total',
                                            default            => $sale['status'],
                                        }) ?>
                                        <?php if (! empty($sale['authorization_status']) && $sale['authorization_status'] !== 'not_required'): ?>
                                            <div class="mt-1">
                                                <?php if ($sale['authorization_status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark" style="font-size: 0.75rem;" title="<?= esc($sale['authorization_reason']) ?>">Pendiente Aut.</span>
                                                <?php elseif ($sale['authorization_status'] === 'approved'): ?>
                                                    <span class="badge bg-success" style="font-size: 0.75rem;" title="Autorizada por Administración">Aut. Aprobada</span>
                                                <?php elseif ($sale['authorization_status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger" style="font-size: 0.75rem;" title="Rechazada por Administración">Aut. Rechazada</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($sale['arca_status'] ?: '-') ?><div class="small text-secondary"><?= esc(strtoupper((string) ($sale['arca_service'] ?? '-'))) ?></div></td>
                                    <td><?= esc(match ($sale['payment_status']) {
                                        'paid'    => 'Pagado',
                                        'partial' => 'Parcial',
                                        default   => 'Pendiente',
                                    }) ?></td>
                                    <td><?= number_format((float) $sale['total'], 2, ',', '.') ?></td>
                                    <td><?= esc(date('d/m/Y H:i', strtotime($sale['issue_date']))) ?></td>
                                    <td class="text-end">
                                        <div class="small text-secondary mb-1"><?= esc($sale['sales_agent_name'] ?: 'Sin vendedor') ?> / <?= esc($sale['sales_zone_name'] ?: 'Sin zona') ?></div>
                                        <a href="<?= site_url('ventas/' . $sale['id'] . '/pdf' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-danger icon-btn" title="Visualizar PDF" aria-label="Visualizar PDF" data-popup="true" data-popup-pdf="true" data-popup-title="Comprobante PDF" data-popup-subtitle="Consulta, imprime o descarga el documento."><i class="bi bi-file-earmark-pdf"></i></a>
                                        <?php
                                        $conversionTargets = match ($sale['document_category'] ?? '') {
                                            'quote'         => ['PEDIDO' => 'Generar pedido', 'FACTURA_B' => 'Generar factura'],
                                            'order'         => ['REMITO' => 'Generar remito', 'FACTURA_B' => 'Generar factura'],
                                            'delivery_note' => ['FACTURA_B' => 'Generar factura'],
                                            'invoice'       => ['NC_B' => 'Nota de Credito', 'ND_B' => 'Nota de Debito'],
                                            'ticket'        => ['NC_B' => 'Nota de Credito', 'ND_B' => 'Nota de Debito'],
                                            default         => [],
                                        };
                                        ?>
                                        <?php foreach ($conversionTargets as $code => $label): ?>
                                            <?php if ($context['canManage'] && ! in_array($sale['status'], ['cancelled', 'returned_total'], true)): ?>
                                                <a href="<?= site_url('ventas/' . $sale['id'] . '/convertir/' . $code . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-primary icon-btn" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>"><i class="bi bi-arrow-left-right"></i></a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if (($sale['authorization_status'] ?? '') === 'pending' && $context['canManage']): ?>
                                            <form method="post" action="<?= site_url('ventas/' . $sale['id'] . '/autorizar-comercial' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-success icon-btn" title="Aprobar Crédito" aria-label="Aprobar Crédito"><i class="bi bi-shield-check"></i></button>
                                            </form>
                                            <form method="post" action="<?= site_url('ventas/' . $sale['id'] . '/rechazar-comercial' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger icon-btn" title="Rechazar Crédito" aria-label="Rechazar Crédito"><i class="bi bi-shield-slash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($sale['status'] === 'draft' && $context['canManage']): ?>
                                            <a href="<?= site_url('ventas/' . $sale['id'] . '/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Editar venta" data-popup-subtitle="Actualizar borrador de venta." title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                                            <form method="post" action="<?= site_url('ventas/' . $sale['id'] . '/confirmar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-success icon-btn" title="Confirmar" aria-label="Confirmar"><i class="bi bi-check-circle"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($sale['status'], ['draft', 'confirmed'], true) && $context['canManage']): ?>
                                            <form method="post" action="<?= site_url('ventas/' . $sale['id'] . '/cancelar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-warning icon-btn" title="Cancelar" aria-label="Cancelar"><i class="bi bi-x-circle"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($sale['status'], ['confirmed', 'returned_partial'], true) && $context['canManage']): ?>
                                            <a href="<?= site_url('ventas/' . $sale['id'] . '/devolucion' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Devolucion" data-popup-subtitle="Registrar devolucion total o parcial." title="Devolucion" aria-label="Devolucion"><i class="bi bi-arrow-counterclockwise"></i></a>
                                        <?php endif; ?>
                                        <?php if ($context['canManage'] && in_array(($sale['document_category'] ?? ''), ['invoice', 'ticket', 'credit_note', 'debit_note'], true) && in_array($sale['status'], ['confirmed', 'returned_partial', 'returned_total'], true) && empty($sale['cae'])): ?>
                                            <form method="post" action="<?= site_url('ventas/' . $sale['id'] . '/arca/autorizar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-primary icon-btn" title="Autorizar ARCA" aria-label="Autorizar ARCA"><i class="bi bi-shield-check"></i></button>
                                            </form>
                                            <form method="post" action="<?= site_url('ventas/' . $sale['id'] . '/arca/consultar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-dark icon-btn" title="Consultar ARCA" aria-label="Consultar ARCA"><i class="bi bi-search"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($sales === []): ?><tr><td colspan="10" class="text-secondary">No hay comprobantes registrados para los filtros seleccionados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-presupuestos" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-0">Presupuestos</h2>
                        <p class="text-secondary small mb-0">Cotizaciones comerciales, validez y conversión a pedidos.</p>
                    </div>
                    <?php if ($context['canManage']): ?>
                        <a href="<?= site_url('ventas/presupuestos/nuevo' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo Presupuesto" data-popup-subtitle="Crear nuevo presupuesto comercial." title="Nuevo Presupuesto" aria-label="Nuevo Presupuesto"><i class="bi bi-plus-lg" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table data-sales-table class="table align-middle mb-0">
                        <thead><tr><th>Nro Presupuesto</th><th>Cliente</th><th>Vendedor</th><th>Total</th><th>Válido hasta</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($quotes ?? [] as $q): ?>
                                <tr>
                                    <td>
                                        <strong class="font-monospace"><?= esc($q['quote_number']) ?></strong>
                                        <div class="small text-secondary"><?= esc($q['created_by_name'] ?: '-') ?></div>
                                    </td>
                                    <td><?= esc($q['customer_name'] ?: ($q['customer_name_snapshot'] ?? 'Consumidor Final')) ?></td>
                                    <td><?= esc($q['sales_agent_name'] ?: 'Sin vendedor') ?></td>
                                    <td><?= number_format((float) ($q['total'] ?? 0), 2, ',', '.') ?> <span class="small text-secondary"><?= esc($q['currency_code'] ?? 'ARS') ?></span></td>
                                    <td><?= !empty($q['valid_until']) ? esc(date('d/m/Y', strtotime($q['valid_until']))) : '<span class="text-secondary">-</span>' ?></td>
                                    <td>
                                        <?php if ($q['status'] === 'draft'): ?>
                                            <span class="badge bg-secondary">Borrador</span>
                                        <?php elseif ($q['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Aprobado</span>
                                        <?php elseif ($q['status'] === 'converted'): ?>
                                            <span class="badge bg-primary">A Pedido</span>
                                        <?php elseif ($q['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rechazado</span>
                                        <?php elseif ($q['status'] === 'expired'): ?>
                                            <span class="badge bg-warning text-dark">Vencido</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= esc($q['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc(date('d/m/Y', strtotime($q['quote_date']))) ?></td>
                                    <td class="text-end">
                                        <?= view('sales/document_actions', ['row'=>$q, 'kind'=>'presupuesto', 'companyId'=>$selectedCompanyId]) ?>
                                        <?php if (($q['source_type'] ?? '') === 'cycle'): ?>
                                            <?php if ($q['status'] === 'draft' && $context['canManage']): ?>
                                                <form method="post" action="<?= site_url('ventas/presupuestos/' . $q['id'] . '/aprobar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-success icon-btn" title="Aprobar Presupuesto" aria-label="Aprobar Presupuesto"><i class="bi bi-check2-circle"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($q['status'], ['draft', 'approved'], true) && $context['canManage']): ?>
                                                <a href="<?= site_url('ventas/presupuestos/' . $q['id'] . '/a-pedido' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-primary icon-btn" data-popup="true" data-popup-title="Generar Pedido" data-popup-subtitle="Convertir presupuesto a pedido." title="Generar Pedido" aria-label="Generar Pedido"><i class="bi bi-cart-plus"></i></a>
                                            <?php endif; ?>
                                            <?php if (in_array($q['status'], ['draft', 'approved', 'expired'], true) && $context['canManage']): ?>
                                                <form method="post" action="<?= site_url('ventas/presupuestos/' . $q['id'] . '/rechazar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline" onsubmit="return confirm('¿Desea rechazar este presupuesto?');">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger icon-btn" title="Rechazar Presupuesto" aria-label="Rechazar Presupuesto"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif (!empty($q['sale_id']) && $context['canManage']): ?>
                                            <?php if (in_array($q['status'], ['draft', 'approved'], true)): ?>
                                                <a href="<?= site_url('ventas/' . $q['sale_id'] . '/convertir/PEDIDO' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-primary icon-btn" title="Generar pedido" aria-label="Generar pedido"><i class="bi bi-arrow-left-right"></i></a>
                                            <?php endif; ?>
                                            <?php if (in_array($q['status'], ['draft', 'approved', 'expired'], true)): ?>
                                                <form method="post" action="<?= site_url('ventas/presupuestos/' . $q['sale_id'] . '/rechazar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline" onsubmit="return confirm('¿Desea rechazar este presupuesto?');">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger icon-btn" title="Rechazar Presupuesto" aria-label="Rechazar Presupuesto"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($quotes)): ?><tr><td colspan="8" class="text-secondary">No hay presupuestos registrados para los filtros seleccionados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-pedidos" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-0">Pedidos de Venta</h2>
                        <p class="text-secondary small mb-0">Gestión de órdenes comerciales, remisión y facturación.</p>
                    </div>
                    <?php if ($context['canManage']): ?>
                        <a href="<?= site_url('ventas/pedidos/nuevo' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo Pedido" data-popup-subtitle="Crear nuevo pedido comercial." title="Nuevo Pedido" aria-label="Nuevo Pedido"><i class="bi bi-plus-lg" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table data-sales-table class="table align-middle mb-0">
                        <thead><tr><th>Nro Pedido</th><th>Cliente</th><th>Vendedor</th><th>Presupuesto</th><th>Total</th><th>Estado</th><th>F. Entrega</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($orders ?? [] as $o): ?>
                                <tr>
                                    <td>
                                        <strong class="font-monospace"><?= esc($o['order_number']) ?></strong>
                                        <div class="small text-secondary"><?= esc($o['created_by_name'] ?: '-') ?></div>
                                    </td>
                                    <td><?= esc($o['customer_name'] ?: ($o['customer_name_snapshot'] ?? 'Consumidor Final')) ?></td>
                                    <td><?= esc($o['sales_agent_name'] ?: 'Sin vendedor') ?></td>
                                    <td><?= !empty($o['quote_number']) ? esc($o['quote_number']) : '<span class="text-secondary">-</span>' ?></td>
                                    <td><?= number_format((float) ($o['total'] ?? 0), 2, ',', '.') ?> <span class="small text-secondary"><?= esc($o['currency_code'] ?? 'ARS') ?></span></td>
                                    <td>
                                        <?php if ($o['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        <?php elseif ($o['status'] === 'approved'): ?>
                                            <span class="badge bg-info text-dark">Aprobado</span>
                                        <?php elseif ($o['status'] === 'partial'): ?>
                                            <span class="badge bg-primary">Entrega Parcial</span>
                                        <?php elseif ($o['status'] === 'fulfilled'): ?>
                                            <span class="badge bg-success">Cumplido</span>
                                        <?php elseif ($o['status'] === 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelado</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= esc($o['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($o['expected_delivery_date']) ? esc(date('d/m/Y', strtotime($o['expected_delivery_date']))) : '<span class="text-secondary">-</span>' ?></td>
                                    <td><?= esc(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                                    <td class="text-end">
                                        <?= view('sales/document_actions', ['row'=>$o, 'kind'=>'pedido', 'companyId'=>$selectedCompanyId]) ?>
                                        <?php if (($o['source_type'] ?? '') === 'cycle'): ?>
                                            <?php if ($o['status'] === 'pending' && $context['canManage']): ?>
                                                <form method="post" action="<?= site_url('ventas/pedidos/' . $o['id'] . '/aprobar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-success icon-btn" title="Aprobar Pedido" aria-label="Aprobar Pedido"><i class="bi bi-check2-circle"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($o['status'], ['pending', 'approved', 'partial'], true) && $context['canManage']): ?>
                                                <a href="<?= site_url('ventas/pedidos/' . $o['id'] . '/remito' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-info icon-btn" data-popup="true" data-popup-title="Generar Remito" data-popup-subtitle="Emitir remito desde pedido." title="Generar Remito" aria-label="Generar Remito"><i class="bi bi-truck"></i></a>
                                                <a href="<?= site_url('ventas/pedidos/' . $o['id'] . '/facturar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-primary icon-btn" data-popup="true" data-popup-title="Facturar Pedido" data-popup-subtitle="Facturar pedido de venta." title="Facturar Pedido" aria-label="Facturar Pedido"><i class="bi bi-receipt"></i></a>
                                            <?php endif; ?>
                                        <?php elseif (!empty($o['sale_id']) && $context['canManage']): ?>
                                            <a href="<?= site_url('ventas/' . $o['sale_id'] . '/convertir/REMITO' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-primary icon-btn" title="Generar remito" aria-label="Generar remito"><i class="bi bi-arrow-left-right"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orders)): ?><tr><td colspan="9" class="text-secondary">No hay pedidos registrados para los filtros seleccionados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-remitos" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-0">Remitos de Entrega</h2>
                        <p class="text-secondary small mb-0">Seguimiento de despachos y entregas de mercadería.</p>
                    </div>
                    <?php if ($context['canManage']): ?>
                        <a href="<?= site_url('ventas/remitos/nuevo' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo Remito" data-popup-subtitle="Crear nuevo remito de entrega." title="Nuevo Remito" aria-label="Nuevo Remito"><i class="bi bi-plus-lg" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table data-sales-table class="table align-middle mb-0">
                        <thead><tr><th>Nro Remito</th><th>Cliente</th><th>Depósito</th><th>Pedido Origen</th><th>Estado</th><th>Transportista / Guía</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($deliveryNotes ?? [] as $dn): ?>
                                <tr>
                                    <td>
                                        <strong class="font-monospace"><?= esc($dn['delivery_number']) ?></strong>
                                        <div class="small text-secondary"><?= esc($dn['created_by_name'] ?: '-') ?></div>
                                    </td>
                                    <td><?= esc($dn['customer_name'] ?: ($dn['customer_name_snapshot'] ?? 'Consumidor Final')) ?></td>
                                    <td><?= esc($dn['warehouse_name'] ?: '-') ?></td>
                                    <td><?= !empty($dn['order_number']) ? esc($dn['order_number']) : '<span class="text-secondary">-</span>' ?></td>
                                    <td>
                                        <?php if ($dn['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        <?php elseif ($dn['status'] === 'dispatched'): ?>
                                            <span class="badge bg-info text-dark">Despachado</span>
                                            <?php if (!empty($dn['dispatched_at'])): ?><div class="small text-secondary"><?= esc(date('d/m/Y H:i', strtotime($dn['dispatched_at']))) ?></div><?php endif; ?>
                                        <?php elseif ($dn['status'] === 'delivered'): ?>
                                            <span class="badge bg-success">Entregado</span>
                                            <?php if (!empty($dn['delivered_at'])): ?><div class="small text-secondary"><?= esc(date('d/m/Y H:i', strtotime($dn['delivered_at']))) ?></div><?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= esc($dn['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= esc($dn['carrier'] ?: '-') ?>
                                        <?php if (!empty($dn['tracking_number'])): ?><div class="small text-secondary">Guía: <?= esc($dn['tracking_number']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= esc(date('d/m/Y', strtotime($dn['delivery_date']))) ?></td>
                                    <td class="text-end">
                                        <?= view('sales/document_actions', ['row'=>$dn, 'kind'=>'remito', 'companyId'=>$selectedCompanyId]) ?>
                                        <?php if ($dn['status'] === 'pending' && $context['canManage']): ?>
                                            <form method="post" action="<?= site_url('ventas/remitos/' . $dn['id'] . '/despachar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-primary icon-btn" title="Despachar y descontar stock" aria-label="Despachar"><i class="bi bi-box-seam"></i></button>
                                            </form>
                                        <?php elseif ($dn['status'] === 'dispatched' && $context['canManage']): ?>
                                            <form method="post" action="<?= site_url('ventas/remitos/' . $dn['id'] . '/entregar' . (!empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-success icon-btn" title="Confirmar entrega al cliente" aria-label="Confirmar Entrega"><i class="bi bi-check2-circle"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($deliveryNotes)): ?><tr><td colspan="8" class="text-secondary">No hay remitos registrados para los filtros seleccionados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<?= view('sales/ui_end') ?>
<?= $this->endSection() ?>
