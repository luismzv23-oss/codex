<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Contabilidad</h1>
        <p class="text-secondary mb-0">Estructura del plan contable, libros y balances.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('contabilidad') ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-list-columns-reverse me-1" title="Plan de Cuentas" aria-label="Plan de Cuentas"></i></a>
        <a href="<?= site_url('contabilidad/diario') ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-journal-text me-1" title="Libro Diario" aria-label="Libro Diario"></i></a>
        <a href="<?= site_url('contabilidad/balance-comprobacion') ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-calculator me-1" title="Balance Comp." aria-label="Balance Comp."></i></a>
        <a href="<?= site_url('contabilidad/balance-general') ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-bar-chart me-1" title="Balance General" aria-label="Balance General"></i></a>
        <a href="<?= site_url('contabilidad/resultados') ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-graph-up me-1" title="Resultados" aria-label="Resultados"></i></a>
        <a href="<?= site_url('contabilidad/asientos/nuevo') ?>" class="btn btn-dark btn-sm" data-popup="true" data-popup-title="Asiento contable" data-popup-subtitle="Registrar nuevo asiento."><i class="bi bi-plus-lg me-1"></i> Nuevo Asiento</a>
    </div>
</div>
<div class="mb-3"><h2 class="h5 mb-1">Estado de Resultados</h2><p class="text-secondary mb-0 small">Periodo: <?= esc($filters['from'] ?? '') ?> a <?= esc($filters['to'] ?? '') ?>.</p></div>
<form class="row g-2 mb-3">
    <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($filters['from'] ?? '') ?>"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($filters['to'] ?? '') ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-dark btn-sm"><i class="bi bi-search"></i></button></div>
</form>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-success text-white rounded-top-4 fw-semibold">Ingresos</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($statement['revenue']['accounts'] ?? [] as $a): ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format((float)($a['balance'] ?? 0), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-success fw-bold"><td>Total Ingresos</td><td class="text-end"><?= number_format((float)($statement['revenue']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-warning text-dark rounded-top-4 fw-semibold">Egresos</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($statement['expenses']['accounts'] ?? [] as $a): ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format(abs((float)($a['balance'] ?? 0)), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-warning fw-bold"><td>Total Egresos</td><td class="text-end"><?= number_format((float)($statement['expenses']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm rounded-4 mt-3">
    <div class="card-body text-center">
        <div class="row">
            <div class="col"><span class="text-secondary">Resultado Neto</span><h3 class="<?= (float)($statement['net_income'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format((float)($statement['net_income'] ?? 0), 2, ',', '.') ?></h3></div>
            <div class="col"><span class="text-secondary">Margen</span><h3><?= number_format((float)($statement['profit_margin'] ?? 0), 1) ?>%</h3></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
