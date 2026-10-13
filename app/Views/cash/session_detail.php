<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>

<?php
$pmLabels = [
    'cash'     => 'Efectivo',
    'card'     => 'Tarjeta',
    'check'    => 'Cheque',
    'transfer' => 'Transferencia',
];
$movTypeLabels = [
    'manual_income'    => 'Ingreso manual',
    'manual_expense'   => 'Egreso manual',
    'withdrawal'       => 'Retiro',
    'transfer_out'     => 'Transferencia salida',
    'transfer_in'      => 'Transferencia entrada',
    'check_endorsement'=> 'Endoso cheque',
    'check_deposit'    => 'Depósito cheque',
    'check_rejection'  => 'Rechazo cheque',
    'sale'             => 'Venta',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Detalle de sesión</h1>
        <p class="text-secondary mb-0"><?= esc($register['name'] ?? '') ?> &mdash; <?= esc($company['name'] ?? '') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('caja/sesiones/' . $session['id'] . '/pdf') ?>" target="_blank" class="btn btn-outline-dark">
            <i class="bi bi-printer me-1"></i>Imprimir PDF
        </a>
        <a href="<?= site_url('caja' . (!empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<!-- Session info + Totales row -->
<div class="row g-4 mb-4">
<div class="col-lg-7">
<div class="card border-0 shadow-sm rounded-4 h-100">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Caja</div>
                <div class="fw-semibold"><?= esc($register['name'] ?? '-') ?></div>
                <div class="small text-secondary"><?= esc($register['code'] ?? '') ?> &middot; <?= esc(ucfirst($register['register_type'] ?? '')) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Estado</div>
                <?php if ($session['status'] === 'open'): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Abierta</span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Cerrada</span>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Apertura</div>
                <div class="fw-semibold"><?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?></div>
                <div class="small text-secondary">por <?= esc($openedBy['name'] ?? '-') ?></div>
            </div>
            <?php if ($session['status'] === 'closed'): ?>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Cierre</div>
                <div class="fw-semibold"><?= esc(date('d/m/Y H:i', strtotime($session['closed_at']))) ?></div>
                <div class="small text-secondary">por <?= esc($closedBy['name'] ?? '-') ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Saldo inicial</div>
                <div class="fw-semibold">$<?= number_format((float) ($session['opening_amount'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Esperado</div>
                <div class="fw-semibold">$<?= number_format((float) ($session['expected_closing_amount'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <?php if ($session['status'] === 'closed'): ?>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Real</div>
                <div class="fw-semibold">$<?= number_format((float) ($session['actual_closing_amount'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-secondary small mb-1">Diferencia</div>
                <?php $diff = (float) ($session['difference_amount'] ?? 0); ?>
                <div class="fw-semibold <?= $diff > 0.005 ? 'text-success' : ($diff < -0.005 ? 'text-danger' : 'text-secondary') ?>">
                    $<?= number_format($diff, 2, ',', '.') ?>
                    <?php if ($diff > 0.005): ?><span class="small">(Sobrante)</span><?php elseif ($diff < -0.005): ?><span class="small">(Faltante)</span><?php else: ?><span class="small">(Cuadrado)</span><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($session['notes'])): ?>
            <div class="col-12">
                <div class="text-secondary small mb-1">Observaciones</div>
                <div class="text-secondary fst-italic"><?= esc($session['notes']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div><!-- /card -->
</div><!-- /col-lg-7 -->

<!-- Totals by payment method -->
<?php if (!empty($paymentMethods)): ?>
<div class="col-lg-5">
<div class="card border-0 shadow-sm rounded-4 h-100">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Totales por medio de pago</h2>
        <div class="row g-3">
            <?php foreach ($paymentMethods as $pm): ?>
                <?php $pmName = $pm['payment_method'] ?: 'Sin medio'; ?>
                <div class="col-6">
                    <div class="bg-light rounded-3 p-3 text-center">
                        <div class="text-secondary small mb-1"><?= esc($pmLabels[strtolower($pmName)] ?? ucfirst($pmName)) ?></div>
                        <div class="fw-semibold <?= (float) $pm['total'] >= 0 ? '' : 'text-danger' ?>">$<?= number_format((float) $pm['total'], 2, ',', '.') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div><!-- /card -->
</div><!-- /col-lg-5 -->
<?php endif; ?>
</div><!-- /row -->

<!-- Sales -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Ventas del turno</h2>
        <?php if (empty($sales)): ?>
            <p class="text-secondary mb-0">No se registraron ventas en este turno.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Cliente</th><th>Medio</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td class="small"><?= esc(date('d/m/Y H:i', strtotime($sale['created_at']))) ?></td>
                        <td><?= esc($sale['document_name'] ?? '-') ?><div class="small text-secondary"><?= esc($sale['document_number'] ?? '') ?></div></td>
                        <td><?= esc($sale['customer_name'] ?? '-') ?></td>
                        <td class="small"><?= esc($pmLabels[strtolower($sale['payment_method'] ?? '')] ?? ucfirst($sale['payment_method'] ?? '-')) ?></td>
                        <td class="text-end">$<?= number_format((float) ($sale['total_amount'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="4" class="text-end">Total facturado:</td>
                        <td class="text-end">$<?= number_format(array_sum(array_column($sales, 'total_amount')), 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Movements -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Movimientos del turno</h2>
        <?php if (empty($movements)): ?>
            <p class="text-secondary mb-0">No se registraron movimientos en este turno.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0" data-codex-pagination="5">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Medio</th><th>Referencia</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $mov): ?>
                    <tr>
                        <td class="small"><?= esc(date('d/m/Y H:i', strtotime($mov['occurred_at']))) ?></td>
                        <td><?= esc($movTypeLabels[$mov['movement_type']] ?? ucfirst($mov['movement_type'])) ?></td>
                        <td class="small"><?= esc($pmLabels[strtolower($mov['payment_method'] ?? '')] ?? ucfirst($mov['payment_method'] ?? '-')) ?></td>
                        <td class="small text-secondary">
                            <?php if (!empty($mov['sale_id']) && !empty($mov['reference_number'])): ?>
                                <a href="<?= site_url('ventas/' . $mov['sale_id'] . '/pdf') ?>" target="_blank" class="text-decoration-none">
                                    <?= esc($mov['reference_number']) ?>
                                    <i class="bi bi-file-earmark-pdf ms-1 text-danger" style="font-size:0.7rem;"></i>
                                </a>
                            <?php else: ?>
                                <?= esc($mov['reference_number'] ?? $mov['gateway_name'] ?? $mov['notes'] ?? '') ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end <?= (float) $mov['amount'] < 0 ? 'text-danger' : '' ?>">$<?= number_format((float) $mov['amount'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
