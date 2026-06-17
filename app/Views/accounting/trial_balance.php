<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Contabilidad</h1>
        <p class="text-secondary mb-0">Estructura del plan contable, libros y balances.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($companies)): ?>
            <form method="get" action="<?= site_url('contabilidad/balance-comprobacion') ?>" class="d-flex gap-2">
                <select name="company_id" class="form-select form-select-sm">
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>>
                            <?= esc($company['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-dark btn-sm icon-btn" title="Cambiar empresa" aria-label="Cambiar empresa"><i class="bi bi-arrow-repeat"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= site_url('contabilidad?company_id=' . $selectedCompanyId) ?>" class="btn btn-outline-dark btn-sm"><i
                class="bi bi-list-columns-reverse me-1" title="Plan de Cuentas" aria-label="Plan de Cuentas"></i></a>
        <a href="<?= site_url('contabilidad/diario?company_id=' . $selectedCompanyId) ?>" class="btn btn-outline-dark btn-sm"><i
                class="bi bi-journal-text me-1" title="Libro Diario" aria-label="Libro Diario"></i></a>
        <a href="<?= site_url('contabilidad/balance-comprobacion?company_id=' . $selectedCompanyId) ?>" class="btn btn-outline-dark btn-sm"><i
                class="bi bi-calculator me-1" title="Balance Comp." aria-label="Balance Comp."></i></a>
        <a href="<?= site_url('contabilidad/balance-general?company_id=' . $selectedCompanyId) ?>" class="btn btn-outline-dark btn-sm"><i
                class="bi bi-bar-chart me-1" title="Balance General" aria-label="Balance General"></i></a>
        <a href="<?= site_url('contabilidad/resultados?company_id=' . $selectedCompanyId) ?>" class="btn btn-outline-dark btn-sm"><i
                class="bi bi-graph-up me-1" title="Resultados" aria-label="Resultados"></i></a>
        <a href="<?= site_url('contabilidad/asientos/nuevo?company_id=' . $selectedCompanyId) ?>" class="btn btn-dark btn-sm icon-btn" data-popup="true" data-popup-title="Asiento contable" data-popup-subtitle="Registrar nuevo asiento." title="Nuevo Asiento" aria-label="Nuevo Asiento"><i class="bi bi-plus-lg"></i></a>
    </div>
</div>
<div class="mb-3">
    <h2 class="h5 mb-1">Balance de Comprobacion</h2>
    <p class="text-secondary mb-0 small">Sumas y saldos al <?= esc($filters['date'] ?? date('Y-m-d')) ?>.</p>
</div>
<form class="row g-2 mb-3">
    <input type="hidden" name="company_id" value="<?= esc($selectedCompanyId) ?>">
    <div class="col-auto"><input type="date" name="date" class="form-control form-control-sm"
            value="<?= esc($filters['date'] ?? '') ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-dark btn-sm"><i class="bi bi-search"></i></button></div>
</form>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Codigo</th>
                    <th>Cuenta</th>
                    <th>Tipo</th>
                    <th class="text-end">Debe</th>
                    <th class="text-end">Haber</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trial['accounts'] ?? [] as $a): ?>
                    <?php if ((float) ($a['total_debit'] ?? 0) == 0 && (float) ($a['total_credit'] ?? 0) == 0)
                        continue; ?>
                    <tr>
                        <td><code><?= esc($a['code'] ?? '') ?></code></td>
                        <td><?= esc($a['name'] ?? '') ?></td>
                        <td><span
                                class="badge bg-<?= match ($a['account_type'] ?? '') { 'asset' => 'primary', 'liability' => 'danger', 'equity' => 'info', 'revenue' => 'success', 'expense' => 'warning', default => 'secondary'} ?>"><?= esc(ucfirst($a['account_type'] ?? '')) ?></span>
                        </td>
                        <td class="text-end"><?= number_format((float) ($a['total_debit'] ?? 0), 2, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) ($a['total_credit'] ?? 0), 2, ',', '.') ?></td>
                        <td class="text-end fw-semibold <?= (float) ($a['balance'] ?? 0) < 0 ? 'text-danger' : '' ?>">


                            <?= number_format((float) ($a['balance'] ?? 0), 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-dark fw-bold">
                    <td colspan="3">TOTALES</td>
                    <td class="text-end"><?= number_format((float) ($trial['total_debit'] ?? 0), 2, ',', '.') ?></td>
                    <td class="text-end">
                        <?= number_format((float) ($trial['total_credit'] ?? 0), 2, ',', '.') ?>
                    </td>
                    <td class="text-end">
                        <?= ($trial['balanced'] ?? false) ? '<span class="text-success">Balanceado</span>' : '<span class="text-danger">Desbalanceado</span>' ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>