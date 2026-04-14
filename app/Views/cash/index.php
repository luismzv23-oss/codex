<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Caja y Tesoreria</h1>
        <p class="text-secondary mb-0">Apertura, cierre y seguimiento diario de ingresos y egresos operativos.</p>
    </div>
    <?php if (! empty($context['canManage'])): ?>
        <div class="d-flex gap-2">
            <a href="<?= site_url('caja/sesiones/apertura/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-dark icon-btn" data-popup="true" data-popup-title="Apertura de caja" data-popup-subtitle="Registrar una nueva sesion activa." title="Abrir caja" aria-label="Abrir caja"><i class="bi bi-box-arrow-in-up"></i></a>
            <a href="<?= site_url('caja/movimientos/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Movimiento de caja" data-popup-subtitle="Registrar un movimiento manual." title="Nuevo movimiento" aria-label="Nuevo movimiento"><i class="bi bi-plus-lg"></i></a>
            <a href="<?= site_url('caja/cheques/nuevo' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cheque" data-popup-subtitle="Registrar cheque recibido o emitido." title="Nuevo cheque" aria-label="Nuevo cheque"><i class="bi bi-journal-check"></i></a>
            <a href="<?= site_url('caja/conciliaciones/nueva' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Conciliacion de caja" data-popup-subtitle="Conciliar por medio de pago la sesion abierta." title="Nueva conciliacion" aria-label="Nueva conciliacion"><i class="bi bi-bank"></i></a>
        </div>
    <?php endif; ?>
</div>

<?php if (! empty($companies)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('caja') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Empresa activa</label>
                    <select name="company_id" class="form-select">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-dark icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Cajas activas</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['registers'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Sesiones abiertas</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['sessions_open'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Ingresos del dia</div><div class="display-6 fw-semibold"><?= number_format((float) ($summary['today_income'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Saldo diario</div><div class="display-6 fw-semibold"><?= number_format((float) ($summary['today_balance'] ?? 0), 2, ',', '.') ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Cheques en cartera</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['checks_portfolio'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small mb-2">Conciliaciones pendientes</div><div class="display-6 fw-semibold"><?= esc((string) ($summary['reconciliations_pending'] ?? 0)) ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Cajas configuradas</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Caja</th><th>Tipo</th><th>Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($registers as $register): ?>
                            <tr>
                                <td><?= esc($register['name']) ?><div class="small text-secondary"><?= esc($register['code']) ?></div></td>
                                <td><?= esc(ucfirst($register['register_type'])) ?></td>
                                <td><?= (int) ($register['active'] ?? 0) === 1 ? 'Activa' : 'Inactiva' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Sesiones abiertas</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Caja</th><th>Apertura</th><th>Esperado</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?= esc($session['register_name']) ?><div class="small text-secondary"><?= esc($session['register_type']) ?></div></td>
                                <td><?= esc(date('d/m/Y H:i', strtotime($session['opened_at']))) ?><div class="small text-secondary">Inicial: <?= number_format((float) ($session['opening_amount'] ?? 0), 2, ',', '.') ?></div></td>
                                <td><?= number_format((float) ($session['expected_closing_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <?php if (! empty($context['canManage'])): ?>
                                        <a href="<?= site_url('caja/sesiones/' . $session['id'] . '/cierre' . (! empty($selectedCompanyId) ? '?company_id=' . $selectedCompanyId : '')) ?>" class="btn btn-sm btn-outline-dark icon-btn" data-popup="true" data-popup-title="Cierre de caja" data-popup-subtitle="Registrar arqueo y cierre de la sesion." title="Cerrar caja" aria-label="Cerrar caja"><i class="bi bi-box-arrow-down"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Resumen por medio</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Medio</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($paymentMethods as $row): ?>
                            <tr>
                                <td><?= esc($row['payment_method'] ?: 'Sin medio') ?></td>
                                <td class="<?= (float) ($row['total'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($paymentMethods === []): ?><tr><td colspan="2" class="text-secondary">Todavia no hay movimientos conciliables por medio.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Cheques</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Cheque</th><th>Tercero</th><th>Vence</th><th>Estado</th><th>Monto</th></tr></thead>
                        <tbody>
                        <?php foreach ($checks as $check): ?>
                            <tr>
                                <td><?= esc($check['check_number']) ?><div class="small text-secondary"><?= esc($check['bank_name']) ?></div></td>
                                <td><?= esc($check['customer_name'] ?: ($check['supplier_name'] ?: '-')) ?></td>
                                <td><?= esc(! empty($check['due_date']) ? date('d/m/Y', strtotime($check['due_date'])) : '-') ?></td>
                                <td><?= esc($check['status']) ?></td>
                                <td><?= number_format((float) ($check['amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($checks === []): ?><tr><td colspan="5" class="text-secondary">No hay cheques registrados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Conciliaciones recientes</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="8">
                        <thead><tr><th>Sesion</th><th>Medio</th><th>Esperado</th><th>Real</th><th>Dif.</th></tr></thead>
                        <tbody>
                        <?php foreach ($reconciliations as $row): ?>
                            <tr>
                                <td><?= esc($row['register_name']) ?><div class="small text-secondary"><?= esc(! empty($row['opened_at']) ? date('d/m/Y H:i', strtotime($row['opened_at'])) : '-') ?></div></td>
                                <td><?= esc($row['payment_method']) ?></td>
                                <td><?= number_format((float) ($row['expected_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= number_format((float) ($row['actual_amount'] ?? 0), 2, ',', '.') ?></td>
                                <td class="<?= (float) ($row['difference_amount'] ?? 0) === 0.0 ? '' : ((float) ($row['difference_amount'] ?? 0) > 0 ? 'text-success' : 'text-danger') ?>"><?= number_format((float) ($row['difference_amount'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($reconciliations === []): ?><tr><td colspan="5" class="text-secondary">Todavia no hay conciliaciones registradas.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Movimientos recientes</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-codex-pagination="10">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Caja</th><th>Referencia</th><th>Canal</th><th>Monto</th></tr></thead>
                        <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?= esc(date('d/m/Y H:i', strtotime($movement['occurred_at']))) ?></td>
                                <td><?= esc($movement['movement_type']) ?></td>
                                <td><?= esc($movement['register_name']) ?></td>
                                <td><?= esc($movement['reference_number'] ?: '-') ?></td>
                                <td><?= esc($movement['gateway_name'] ?: ($movement['check_number'] ? 'Cheque ' . $movement['check_number'] : ($movement['payment_method'] ?: '-'))) ?></td>
                                <td class="<?= (float) ($movement['amount'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format((float) ($movement['amount'] ?? 0), 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
