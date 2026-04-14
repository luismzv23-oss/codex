<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Cobranzas</h1>
        <p class="text-secondary mb-0">Cuenta corriente, saldos por cobrar y recibos aplicados a ventas.</p>
    </div>
    <div class="d-flex gap-2">
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
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Comprobantes pendientes</div><div class="display-6 fw-semibold text-warning"><?= esc((string) ($receivableSummary['pending'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-secondary">Saldo por cobrar</div><div class="display-6 fw-semibold"><?= number_format((float) ($receivableSummary['balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Cuenta corriente pendiente</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Comprobante</th><th>Cliente</th><th>Vencimiento</th><th>Saldo</th></tr></thead>
                        <tbody>
                        <?php foreach ($receivables as $receivable): ?>
                            <tr>
                                <td><?= esc($receivable['document_number']) ?><div class="small text-secondary"><?= esc($receivable['sale_status'] ?? '-') ?></div></td>
                                <td><?= esc($receivable['customer_name'] ?: '-') ?></td>
                                <td><?= esc(! empty($receivable['due_date']) ? date('d/m/Y', strtotime($receivable['due_date'])) : '-') ?></td>
                                <td><?= number_format((float) ($receivable['balance_amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($receivables === []): ?><tr><td colspan="4" class="text-secondary">No hay saldos pendientes.</td></tr><?php endif; ?>
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
                        <thead><tr><th>Recibo</th><th>Cliente</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                            <tr>
                                <td><?= esc($receipt['receipt_number']) ?><div class="small text-secondary"><?= esc(date('d/m/Y H:i', strtotime($receipt['issue_date']))) ?></div></td>
                                <td><?= esc($receipt['customer_name']) ?></td>
                                <td><?= number_format((float) ($receipt['total_amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($receipts === []): ?><tr><td colspan="3" class="text-secondary">No hay recibos registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
