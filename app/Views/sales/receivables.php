<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Cobranzas</h1>
        <p class="text-secondary mb-0">Cuenta corriente, saldos por cobrar y recibos aplicados a ventas.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (! empty($companies)): ?>
            <form method="get" action="<?= site_url('ventas/cobranzas') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <?php if ($context['canManage']): ?>
            <a href="<?= site_url('ventas/cobranzas/nuevo' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Nuevo recibo" data-popup-subtitle="Aplicar cobranza a comprobantes pendientes." title="Nuevo recibo" aria-label="Nuevo recibo"><i class="bi bi-cash-coin"></i></a>
        <?php endif; ?>
        <a href="<?= site_url('ventas' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" title="Volver a ventas" aria-label="Volver a ventas"><i class="bi bi-arrow-left"></i></a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Comprobantes pendientes</div><div class="display-6 fw-semibold text-warning"><?= esc((string) ($receivableSummary['pending'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Saldo por cobrar</div><div class="display-6 fw-semibold"><?= number_format((float) ($receivableSummary['balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Vencidos</div><div class="display-6 fw-semibold text-danger"><?= esc((string) ($receivableSummary['overdue'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Recibos emitidos</div><div class="display-6 fw-semibold text-success"><?= esc((string) ($receivableSummary['receipts_count'] ?? 0)) ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Cuenta corriente pendiente</h2>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="receivable-customer-filter" class="form-select form-select-sm" style="width: auto; min-width: 180px;">
                            <option value="">Todos los clientes</option>
                            <?php
                            $customerNames = [];
                            foreach ($receivables as $r) {
                                $cname = $r['customer_name'] ?: 'Sin cliente';
                                if (! in_array($cname, $customerNames, true)) {
                                    $customerNames[] = $cname;
                                }
                            }
                            sort($customerNames);
                            foreach ($customerNames as $cname): ?>
                                <option value="<?= esc($cname) ?>"><?= esc($cname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="receivables-table">
                        <thead><tr><th>Comprobante</th><th>Cliente</th><th>Vencimiento</th><th>Total</th><th>Cobrado</th><th>Saldo</th></tr></thead>
                        <tbody>
                        <?php foreach ($receivables as $receivable): ?>
                            <?php
                            $isOverdue = ! empty($receivable['due_date']) && strtotime($receivable['due_date']) < time() && ($receivable['status'] ?? '') !== 'paid';
                            $customerName = $receivable['customer_name'] ?: 'Sin cliente';
                            ?>
                            <tr data-customer="<?= esc($customerName) ?>" class="<?= $isOverdue ? 'table-danger' : '' ?>">
                                <td>
                                    <?= esc($receivable['document_number']) ?>
                                    <div class="small text-secondary">
                                        <?= esc($receivable['sale_status'] ?? '-') ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger ms-1">Vencido</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= esc($customerName) ?></td>
                                <td>
                                    <?= esc(! empty($receivable['due_date']) ? date('d/m/Y', strtotime($receivable['due_date'])) : '-') ?>
                                    <?php if ($isOverdue && ! empty($receivable['due_date'])): ?>
                                        <div class="small text-danger"><?= (int) ((time() - strtotime($receivable['due_date'])) / 86400) ?> dias</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format((float) ($receivable['total_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= number_format((float) ($receivable['paid_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="fw-semibold"><?= number_format((float) ($receivable['balance_amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($receivables === []): ?><tr><td colspan="6" class="text-secondary">No hay saldos pendientes.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Recibos recientes</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Recibo</th><th>Cliente</th><th>Metodo</th><th>Total</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                            <?php $isVoided = ($receipt['status'] ?? '') === 'voided'; ?>
                            <tr class="<?= $isVoided ? 'table-secondary text-decoration-line-through' : '' ?>">
                                <td>
                                    <?= esc($receipt['receipt_number']) ?>
                                    <div class="small text-secondary"><?= esc(date('d/m/Y H:i', strtotime($receipt['issue_date']))) ?></div>
                                </td>
                                <td><?= esc($receipt['customer_name']) ?></td>
                                <td>
                                    <?php
                                    $methodLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'check' => 'Cheque', 'qr' => 'QR', 'mixed' => 'Mixto'];
                                    echo esc($methodLabels[$receipt['payment_method'] ?? ''] ?? ($receipt['payment_method'] ?? '-'));
                                    ?>
                                </td>
                                <td><?= number_format((float) ($receipt['total_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td>
                                    <?php if ($isVoided): ?>
                                        <span class="badge bg-secondary">Anulado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Aplicado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/detalle' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark btn-sm icon-btn" data-popup="true" data-popup-title="Detalle de recibo" data-popup-subtitle="Comprobantes aplicados y datos del recibo." title="Ver detalle" aria-label="Ver detalle"><i class="bi bi-eye"></i></a>
                                        <?php if ($context['canManage'] && ! $isVoided): ?>
                                            <form method="post" action="<?= site_url('ventas/cobranzas/' . $receipt['id'] . '/anular' . (! empty($companies) ? '?company_id=' . $selectedCompanyId : '')) ?>" onsubmit="return confirm('¿Anular este recibo? Se revertiran los saldos aplicados.')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm icon-btn" title="Anular recibo" aria-label="Anular recibo"><i class="bi bi-x-circle"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($receipts === []): ?><tr><td colspan="6" class="text-secondary">No hay recibos registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const filter = document.getElementById('receivable-customer-filter');
    if (!filter) return;
    filter.addEventListener('change', () => {
        const selected = filter.value;
        document.querySelectorAll('#receivables-table tbody tr').forEach(row => {
            if (!selected || row.dataset.customer === selected) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
})();
</script>
<?= $this->endSection() ?>
