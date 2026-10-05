<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('purchases/shell', get_defined_vars()) ?>
<?= view('purchases/overview', get_defined_vars()) ?>
<!-- Interactive Search Toolbar -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="input-group" style="max-width: 400px;">
            <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-search"></i></span>
            <input type="text" id="purchasesSearchInput" class="form-control border-start-0 ps-0 shadow-none" placeholder="Filtrar compras por Proveedor, número, etc..." aria-label="Buscar compras">
            <button class="btn btn-white border border-start-0 text-secondary" type="button" id="clearPurchasesSearchBtn" style="display: none;" title="Limpiar busqueda"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="small text-secondary">
            <i class="bi bi-info-circle me-1"></i> Haz clic en el nombre de un proveedor para filtrar instantáneamente todo su historial.
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Proveedores</h2>
                <p class="text-secondary mb-3">Base comercial activa por empresa.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="suppliers-table">
                        <thead><tr><th>Proveedor</th><th>Contacto</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr class="data-row" data-supplier="<?= esc($supplier['name']) ?>">
                                <td>
                                    <div class="fw-semibold supplier-name-trigger text-primary" style="cursor: pointer;" data-supplier="<?= esc($supplier['name']) ?>"><?= esc($supplier['name']) ?></div>
                                    <div class="small text-secondary"><?= esc($supplier['tax_id'] ?: 'Sin identificacion fiscal') ?></div>
                                </td>
                                <td>
                                    <div class="small text-secondary"><?= esc($supplier['email'] ?: '-') ?></div>
                                    <div class="small text-secondary"><?= esc($supplier['phone'] ?: '-') ?></div>
                                </td>
                                <td class="text-end">
                                    <?php if ($context['canManage']): ?>
                                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                                            <a href="<?= site_url('compras/proveedores/' . $supplier['id'] . '/editar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-secondary icon-btn" data-popup="true" data-popup-title="Editar proveedor" data-popup-subtitle="Modificar datos del proveedor." title="Editar"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="<?= site_url('compras/proveedores/' . $supplier['id'] . '/eliminar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="d-inline" onsubmit="return confirm('¿Confirma eliminar este proveedor?');">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger icon-btn" title="Eliminar"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="3" class="text-secondary text-center py-3">No se encontraron proveedores.</td></tr>
                        <?php if ($suppliers === []): ?><tr class="no-data-row"><td colspan="3" class="text-secondary">No hay proveedores registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Ordenes de compra</h2>
                <p class="text-secondary mb-3">Circuito documental de abastecimiento.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="orders-table">
                        <thead><tr><th>Orden</th><th>Proveedor</th><th>Deposito</th><th>Estado</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr class="data-row" data-supplier="<?= esc($order['supplier_name']) ?>" data-number="<?= esc($order['order_number']) ?>">
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
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron órdenes de compra.</td></tr>
                        <?php if ($orders === []): ?><tr class="no-data-row"><td colspan="6" class="text-secondary">No hay ordenes registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Recepciones</h2>
                <p class="text-secondary mb-3">Ingresos a Inventario y origen de deuda con proveedores.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="receipts-table">
                        <thead><tr><th>Recepcion</th><th>Proveedor</th><th>Orden</th><th>Deposito</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                            <tr class="data-row" data-supplier="<?= esc($receipt['supplier_name']) ?>" data-number="<?= esc($receipt['receipt_number']) ?>">
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
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron recepciones.</td></tr>
                        <?php if ($receipts === []): ?><tr class="no-data-row"><td colspan="6" class="text-secondary">No hay recepciones registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Cuentas a pagar</h2>
                <p class="text-secondary mb-3">Base financiera inicial del circuito de compras.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="payables-table">
                        <thead><tr><th>Cuenta</th><th>Proveedor</th><th>Recepcion</th><th>Estado</th><th>Saldo (negativo: a favor)</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($payables as $payable): ?>
                            <tr class="data-row" data-supplier="<?= esc($payable['supplier_name']) ?>" data-number="<?= esc($payable['payable_number']) ?>">
                                <td><?= esc($payable['payable_number']) ?><div class="small text-secondary"><?= esc(! empty($payable['due_date']) ? date('d/m/Y H:i', strtotime($payable['due_date'])) : '-') ?></div></td>
                                <td><?= esc($payable['supplier_name']) ?></td>
                                <td><?= esc($payable['receipt_number'] ?: 'Factura directa') ?></td>
                                <td><?= esc($payable['status']) ?></td>
                                <td><?= number_format((float) $payable['balance_amount'], 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if ($context['canManage'] && in_array($payable['status'], ['pending', 'partial'], true)): ?>
                                        <a href="<?= site_url('compras/cuentas/' . $payable['id'] . '/pagar' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Pago a proveedor" data-popup-subtitle="Registrar pago imputado a la cuenta seleccionada." title="Pagar" aria-label="Pagar"><i class="bi bi-cash-coin"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="6" class="text-secondary text-center py-3">No se encontraron cuentas a pagar.</td></tr>
                        <?php if ($payables === []): ?><tr class="no-data-row"><td colspan="6" class="text-secondary">No hay cuentas a pagar registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Facturas proveedor</h2>
                <p class="text-secondary mb-3">Documentacion comercial y financiera del abastecimiento.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="invoices-table">
                        <thead><tr><th>Factura</th><th>Proveedor</th><th>Recepcion</th><th>Moneda</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="data-row" data-supplier="<?= esc($invoice['supplier_name']) ?>" data-number="<?= esc($invoice['invoice_number']) ?>">
                                <td><?= esc($invoice['invoice_number']) ?><div class="small text-secondary"><?= esc(! empty($invoice['issue_date']) ? date('d/m/Y H:i', strtotime($invoice['issue_date'])) : '-') ?></div></td>
                                <td><?= esc($invoice['supplier_name']) ?></td>
                                <td><?= esc($invoice['receipt_number'] ?: '-') ?></td>
                                <td><?= esc($invoice['currency_code']) ?></td>
                                <td><?= number_format((float) ($invoice['total'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="5" class="text-secondary text-center py-3">No se encontraron facturas.</td></tr>
                        <?php if ($invoices === []): ?><tr class="no-data-row"><td colspan="5" class="text-secondary">No hay facturas proveedor registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Notas de credito proveedor</h2>
                <p class="text-secondary mb-3">Ajustes financieros aplicados a facturas de compra.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="credit-notes-table">
                        <thead><tr><th>NC</th><th>Proveedor</th><th>Factura</th><th>Monto</th></tr></thead>
                        <tbody>
                        <?php foreach ($creditNotes as $row): ?>
                            <tr class="data-row" data-supplier="<?= esc($row['supplier_name']) ?>" data-number="<?= esc($row['credit_note_number']) ?>">
                                <td><?= esc($row['credit_note_number']) ?><div class="small text-secondary"><?= esc(! empty($row['issue_date']) ? date('d/m/Y H:i', strtotime($row['issue_date'])) : '-') ?></div></td>
                                <td><?= esc($row['supplier_name']) ?></td>
                                <td><?= esc($row['invoice_number'] ?: '-') ?></td>
                                <td><?= number_format((float) ($row['amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="4" class="text-secondary text-center py-3">No se encontraron notas de crédito.</td></tr>
                        <?php if ($creditNotes === []): ?><tr class="no-data-row"><td colspan="4" class="text-secondary">No hay notas de credito registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <div class="purchases-list-heading"><div class="purchases-list-title"><h2 class="h4 mb-1">Costo historico por proveedor</h2>
                <p class="text-secondary mb-3">Ultimos costos observados por producto y proveedor.</p></div><div class="purchases-list-filter"></div></div>
                <div class="table-responsive">
                    <table data-purchases-table class="table align-middle mb-0" id="cost-history-table">
                        <thead><tr><th>Producto</th><th>Proveedor</th><th>Moneda</th><th>Costo</th><th>Fecha</th></tr></thead>
                        <tbody>
                        <?php foreach ($costHistory as $row): ?>
                            <tr class="data-row" data-supplier="<?= esc($row['supplier_name']) ?>" data-number="<?= esc($row['sku']) ?>">
                                <td><?= esc($row['product_name']) ?><div class="small text-secondary"><?= esc($row['sku']) ?></div></td>
                                <td><?= esc($row['supplier_name']) ?></td>
                                <td><?= esc($row['currency_code']) ?></td>
                                <td><?= number_format((float) ($row['unit_cost'] ?? 0), 4, ',', '.') ?></td>
                                <td><?= esc(! empty($row['observed_at']) ? date('d/m/Y H:i', strtotime($row['observed_at'])) : '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="no-results-row" style="display: none;"><td colspan="5" class="text-secondary text-center py-3">No se encontró historial de costos.</td></tr>
                        <?php if ($costHistory === []): ?><tr class="no-data-row"><td colspan="5" class="text-secondary">Todavia no hay historial de costos.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= view('purchases/end') ?>
<?= $this->endSection() ?>
