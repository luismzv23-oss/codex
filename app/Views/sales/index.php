<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Ventas</h1>
        <p class="text-secondary mb-0">Ventas, clientes, pagos y devoluciones integradas con inventario.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('ventas') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
            <a href="<?= site_url('ventas/reportes' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Reportes</a>
            <a href="<?= site_url('ventas/cobranzas' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Cobranzas</a>
            <?php if ($context['canManage']): ?>
            <a href="<?= site_url('ventas/vendedores/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Vendedor" data-popup-subtitle="Registrar responsable comercial.">Vendedores</a>
            <a href="<?= site_url('ventas/zonas/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Zona comercial" data-popup-subtitle="Registrar zona comercial.">Zonas</a>
            <a href="<?= site_url('ventas/condiciones/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Condicion comercial" data-popup-subtitle="Registrar condicion de venta.">Condiciones</a>
            <a href="<?= site_url('ventas/pos' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">POS</a>
            <a href="<?= site_url('ventas/kiosco' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark">Kiosco</a>
            <a href="<?= site_url('ventas/listas-precio/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Lista de precios" data-popup-subtitle="Configurar precios comerciales por producto.">Lista de precios</a>
            <a href="<?= site_url('ventas/promociones/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Promocion" data-popup-subtitle="Crear promociones comerciales activas.">Promociones</a>
        <?php endif; ?>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('ventas/clientes/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Cliente" data-popup-subtitle="Alta rapida de cliente para ventas.">Nuevo cliente</a>
            <!--   <a href="<?= site_url('ventas/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Venta" data-popup-subtitle="Crear venta integrada con inventario.">Nueva venta</a>-->
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Borradores</div><div class="display-6 fw-semibold"><?= esc((string) $summary['drafts']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Confirmadas</div><div class="display-6 fw-semibold text-success"><?= esc((string) $summary['confirmed']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Devueltas</div><div class="display-6 fw-semibold text-warning"><?= esc((string) $summary['returned']) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Monto total</div><div class="display-6 fw-semibold"><?= number_format((float) $summary['total_amount'], 2, ',', '.') ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Facturacion estandar</div><div class="display-6 fw-semibold"><?= esc((string) $summary['standard']) ?></div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Facturacion kiosco</div><div class="display-6 fw-semibold"><?= esc((string) $summary['kiosk']) ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Cuenta corriente pendiente</div><div class="display-6 fw-semibold text-warning"><?= esc((string) ($summary['receivable_pending'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Saldo por cobrar</div><div class="display-6 fw-semibold"><?= number_format((float) ($summary['receivable_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>

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
                <?php foreach (array_slice($priceLists, 0, 5) as $priceList): ?>
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <strong><?= esc($priceList['name']) ?></strong>
                                <div class="small text-secondary"><?= esc($priceList['description'] ?: 'Sin descripcion') ?></div>
                            </div>
                            <span class="small <?= (int) ($priceList['is_default'] ?? 0) === 1 ? 'text-success' : 'text-secondary' ?>"><?= (int) ($priceList['is_default'] ?? 0) === 1 ? 'Base' : 'Activa' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($priceLists === []): ?><div class="text-secondary">No hay listas de precio creadas.</div><?php endif; ?>
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
                <?php foreach (array_slice($promotions, 0, 5) as $promotion): ?>
                    <div class="border rounded-3 p-3 mb-2">
                        <strong><?= esc($promotion['name']) ?></strong>
                        <div class="small text-secondary">
                            <?= esc($promotion['promotion_type'] === 'percent' ? number_format((float) $promotion['value'], 2, ',', '.') . '% off' : 'Descuento fijo ' . number_format((float) $promotion['value'], 2, ',', '.')) ?>
                            / <?= esc($promotion['scope'] === 'all' ? 'Todos los productos' : 'Productos seleccionados') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($promotions === []): ?><div class="text-secondary">No hay promociones activas.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="get" action="<?= site_url('ventas') ?>" class="row g-3 align-items-end">
            <?php if (! empty($companies)): ?><input type="hidden" name="company_id" value="<?= esc($selectedCompanyId) ?>"><?php endif; ?>
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
            <div class="col-md-1"><button class="btn btn-dark w-100">Filtrar</button></div>
            <div class="col-md-1"><a href="<?= site_url('ventas' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark w-100">Limpiar</a></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0" data-codex-pagination="8">
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
                            <td><?= esc(match ($sale['status']) {
                                'draft' => 'Borrador',
                                'confirmed' => 'Confirmada',
                                'cancelled' => 'Cancelada',
                                'returned_partial' => 'Devuelta parcial',
                                'returned_total' => 'Devuelta total',
                                default => $sale['status'],
                            }) ?></td>
                            <td><?= esc($sale['arca_status'] ?: '-') ?><div class="small text-secondary"><?= esc(strtoupper((string) ($sale['arca_service'] ?? '-'))) ?></div></td>
                            <td><?= esc(match ($sale['payment_status']) {
                                'paid' => 'Pagado',
                                'partial' => 'Parcial',
                                default => 'Pendiente',
                            }) ?></td>
                            <td><?= number_format((float) $sale['total'], 2, ',', '.') ?></td>
                            <td><?= esc(date('d/m/Y H:i', strtotime($sale['issue_date']))) ?></td>
                            <td class="text-end">
                                <div class="small text-secondary mb-1"><?= esc($sale['sales_agent_name'] ?: 'Sin vendedor') ?> / <?= esc($sale['sales_zone_name'] ?: 'Sin zona') ?></div>
                                <a href="<?= site_url('ventas/' . $sale['id'] . '/pdf' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-danger icon-btn" title="PDF" aria-label="PDF" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>
                                <?php
                                $conversionTargets = match ($sale['document_category'] ?? '') {
                                    'quote' => ['PEDIDO' => 'Generar pedido', 'FACTURA_B' => 'Generar factura'],
                                    'order' => ['REMITO' => 'Generar remito', 'FACTURA_B' => 'Generar factura'],
                                    'delivery_note' => ['FACTURA_B' => 'Generar factura'],
                                    default => [],
                                };
                                ?>
                                <?php foreach ($conversionTargets as $code => $label): ?>
                                    <?php if ($context['canManage'] && ! in_array($sale['status'], ['cancelled', 'returned_total'], true)): ?>
                                        <a href="<?= site_url('ventas/' . $sale['id'] . '/convertir/' . $code . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-primary icon-btn" title="<?= esc($label) ?>" aria-label="<?= esc($label) ?>"><i class="bi bi-arrow-left-right"></i></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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
                                <?php if ($context['canManage'] && in_array(($sale['document_category'] ?? ''), ['invoice', 'ticket', 'credit_note', 'debit_note'], true) && in_array($sale['status'], ['confirmed', 'returned_partial', 'returned_total'], true)): ?>
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
                    <?php if ($sales === []): ?><tr><td colspan="10" class="text-secondary">No hay ventas registradas para los filtros seleccionados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
