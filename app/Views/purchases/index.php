<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Compras</h1>
        <p class="text-secondary mb-0">Proveedores, ordenes, recepciones, devoluciones y cuentas a pagar integradas con inventario.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('compras') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('compras/proveedores/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Proveedor" data-popup-subtitle="Alta operativa de proveedor.">Proveedor</a>
            <a href="<?= site_url('compras/ordenes/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark" data-popup="true" data-popup-title="Orden de compra" data-popup-subtitle="Registrar una nueva orden de compra.">Nueva orden</a>
            <a href="<?= site_url('compras/facturas/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Factura proveedor" data-popup-subtitle="Registrar una factura del proveedor.">Factura</a>
            <a href="<?= site_url('compras/notas-credito/nueva' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark" data-popup="true" data-popup-title="Nota de credito proveedor" data-popup-subtitle="Registrar una nota de credito del proveedor.">NC</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Proveedores activos</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['suppliers'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Ordenes en borrador</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['orders_draft'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Ordenes aprobadas</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['orders_approved'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Recepciones registradas</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['receipts'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Cuentas pendientes</div><div class="display-6 fw-semibold text-warning"><?= esc((string) ($summary['payables_pending'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Saldo a pagar</div><div class="display-6 fw-semibold"><?= number_format((float) ($summary['payables_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Proveedores</h2>
                <p class="text-secondary mb-3">Base comercial activa por empresa.</p>
                <?php foreach ($suppliers as $supplier): ?>
                    <div class="border rounded-4 p-3 mb-2">
                        <div class="fw-semibold"><?= esc($supplier['name']) ?></div>
                        <div class="small text-secondary"><?= esc($supplier['tax_id'] ?: 'Sin identificacion fiscal') ?></div>
                        <div class="small text-secondary"><?= esc($supplier['email'] ?: $supplier['phone'] ?: '-') ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($suppliers === []): ?><div class="text-secondary">No hay proveedores registrados.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Ordenes de compra</h2>
                <p class="text-secondary mb-3">Circuito documental de abastecimiento.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Orden</th><th>Proveedor</th><th>Deposito</th><th>Estado</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= esc($order['order_number']) ?><div class="small text-secondary"><?= esc(! empty($order['issued_at']) ? date('d/m/Y H:i', strtotime($order['issued_at'])) : '-') ?></div></td>
                                <td><?= esc($order['supplier_name']) ?></td>
                                <td><?= esc($order['warehouse_name'] ?: '-') ?></td>
                                <td><?= esc($order['status']) ?></td>
                                <td><?= number_format((float) $order['total'], 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if ($context['canManage'] && ($order['status'] ?? '') === 'draft'): ?>
                                        <form method="post" action="<?= site_url('compras/ordenes/' . $order['id'] . '/confirmar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-success icon-btn" title="Aprobar" aria-label="Aprobar"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($context['canManage'] && in_array($order['status'] ?? '', ['approved', 'received_partial'], true)): ?>
                                        <a href="<?= site_url('compras/ordenes/' . $order['id'] . '/recepcionar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Recepcion de compra" data-popup-subtitle="Registrar ingreso real de mercaderia." title="Recepcionar" aria-label="Recepcionar"><i class="bi bi-box-arrow-in-down"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($orders === []): ?><tr><td colspan="6" class="text-secondary">No hay ordenes registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Recepciones</h2>
                <p class="text-secondary mb-3">Ingresos a Inventario y origen de deuda con proveedores.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Recepcion</th><th>Proveedor</th><th>Orden</th><th>Deposito</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                            <tr>
                                <td><?= esc($receipt['receipt_number']) ?><div class="small text-secondary"><?= esc(! empty($receipt['received_at']) ? date('d/m/Y H:i', strtotime($receipt['received_at'])) : '-') ?></div></td>
                                <td><?= esc($receipt['supplier_name']) ?></td>
                                <td><?= esc($receipt['order_number'] ?: '-') ?></td>
                                <td><?= esc($receipt['warehouse_name']) ?></td>
                                <td><?= number_format((float) $receipt['total'], 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if ($context['canManage']): ?>
                                        <a href="<?= site_url('compras/recepciones/' . $receipt['id'] . '/devolver' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-warning icon-btn" data-popup="true" data-popup-title="Devolucion a proveedor" data-popup-subtitle="Registrar egreso al proveedor sobre una recepcion." title="Devolver" aria-label="Devolver"><i class="bi bi-arrow-return-left"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($receipts === []): ?><tr><td colspan="6" class="text-secondary">No hay recepciones registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Cuentas a pagar</h2>
                <p class="text-secondary mb-3">Base financiera inicial del circuito de compras.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Cuenta</th><th>Proveedor</th><th>Recepcion</th><th>Estado</th><th>Saldo</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($payables as $payable): ?>
                            <tr>
                                <td><?= esc($payable['payable_number']) ?><div class="small text-secondary"><?= esc(! empty($payable['due_date']) ? date('d/m/Y H:i', strtotime($payable['due_date'])) : '-') ?></div></td>
                                <td><?= esc($payable['supplier_name']) ?></td>
                                <td><?= esc($payable['receipt_number']) ?></td>
                                <td><?= esc($payable['status']) ?></td>
                                <td><?= number_format((float) $payable['balance_amount'], 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if ($context['canManage'] && in_array($payable['status'], ['pending', 'partial'], true)): ?>
                                        <a href="<?= site_url('compras/cuentas/' . $payable['id'] . '/pagar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Pago a proveedor" data-popup-subtitle="Registrar pago imputado a la cuenta seleccionada." title="Pagar" aria-label="Pagar"><i class="bi bi-cash-coin"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($payables === []): ?><tr><td colspan="6" class="text-secondary">No hay cuentas a pagar registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Facturas proveedor</h2>
                <p class="text-secondary mb-3">Documentacion comercial y financiera del abastecimiento.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Factura</th><th>Proveedor</th><th>Recepcion</th><th>Moneda</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?= esc($invoice['invoice_number']) ?><div class="small text-secondary"><?= esc(! empty($invoice['issue_date']) ? date('d/m/Y H:i', strtotime($invoice['issue_date'])) : '-') ?></div></td>
                                <td><?= esc($invoice['supplier_name']) ?></td>
                                <td><?= esc($invoice['receipt_number'] ?: '-') ?></td>
                                <td><?= esc($invoice['currency_code']) ?></td>
                                <td><?= number_format((float) ($invoice['total'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($invoices === []): ?><tr><td colspan="5" class="text-secondary">No hay facturas proveedor registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Notas de credito proveedor</h2>
                <p class="text-secondary mb-3">Ajustes financieros aplicados a facturas de compra.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>NC</th><th>Proveedor</th><th>Factura</th><th>Monto</th></tr></thead>
                        <tbody>
                        <?php foreach ($creditNotes as $row): ?>
                            <tr>
                                <td><?= esc($row['credit_note_number']) ?><div class="small text-secondary"><?= esc(! empty($row['issue_date']) ? date('d/m/Y H:i', strtotime($row['issue_date'])) : '-') ?></div></td>
                                <td><?= esc($row['supplier_name']) ?></td>
                                <td><?= esc($row['invoice_number'] ?: '-') ?></td>
                                <td><?= number_format((float) ($row['amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($creditNotes === []): ?><tr><td colspan="4" class="text-secondary">No hay notas de credito registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-1">Costo historico por proveedor</h2>
                <p class="text-secondary mb-3">Ultimos costos observados por producto y proveedor.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="10">
                        <thead><tr><th>Producto</th><th>Proveedor</th><th>Moneda</th><th>Costo</th><th>Fecha</th></tr></thead>
                        <tbody>
                        <?php foreach ($costHistory as $row): ?>
                            <tr>
                                <td><?= esc($row['product_name']) ?><div class="small text-secondary"><?= esc($row['sku']) ?></div></td>
                                <td><?= esc($row['supplier_name']) ?></td>
                                <td><?= esc($row['currency_code']) ?></td>
                                <td><?= number_format((float) ($row['unit_cost'] ?? 0), 4, ',', '.') ?></td>
                                <td><?= esc(! empty($row['observed_at']) ? date('d/m/Y H:i', strtotime($row['observed_at'])) : '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($costHistory === []): ?><tr><td colspan="5" class="text-secondary">Todavia no hay historial de costos.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
