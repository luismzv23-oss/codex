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
<div class="mb-3"><h2 class="h5 mb-1">Balance General</h2><p class="text-secondary mb-0 small">Situacion patrimonial al <?= esc($filters['date'] ?? date('Y-m-d')) ?>.</p></div>
<form class="row g-2 mb-3">
    <div class="col-auto"><input type="date" name="date" class="form-control form-control-sm" value="<?= esc($filters['date'] ?? '') ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-dark btn-sm"><i class="bi bi-search"></i></button></div>
</form>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-primary text-white rounded-top-4 fw-semibold">Activo</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($balance['assets']['accounts'] ?? [] as $a): ?>
                        <?php if ((float)($a['balance'] ?? 0) == 0) continue; ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format((float)($a['balance'] ?? 0), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold"><td>Total Activo</td><td class="text-end"><?= number_format((float)($balance['assets']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-danger text-white rounded-top-4 fw-semibold">Pasivo</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($balance['liabilities']['accounts'] ?? [] as $a): ?>
                        <?php if ((float)($a['balance'] ?? 0) == 0) continue; ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format(abs((float)($a['balance'] ?? 0)), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-danger fw-bold"><td>Total Pasivo</td><td class="text-end"><?= number_format((float)($balance['liabilities']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </table>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-info text-white rounded-top-4 fw-semibold">Patrimonio Neto</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <?php foreach ($balance['equity']['accounts'] ?? [] as $a): ?>
                        <?php if ((float)($a['balance'] ?? 0) == 0) continue; ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format(abs((float)($a['balance'] ?? 0)), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td class="fst-italic">Resultado del ejercicio</td><td class="text-end"><?= number_format((float)($balance['equity']['net_income'] ?? 0), 2, ',', '.') ?></td></tr>
                    <tr class="table-info fw-bold"><td>Total PN</td><td class="text-end"><?= number_format((float)($balance['equity']['total_with_income'] ?? 0), 2, ',', '.') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="mt-3 text-center"><?= ($balance['balanced'] ?? false) ? '<span class="badge bg-success fs-6">Activo = Pasivo + PN ✓</span>' : '<span class="badge bg-danger fs-6">Desbalanceado ✗</span>' ?></div>
<?= $this->endSection() ?>
