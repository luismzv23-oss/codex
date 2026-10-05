<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $section = 'income_statement'; ?>
<?= view('accounting/shell', get_defined_vars()) ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-success text-white rounded-top-4 fw-semibold">Ingresos</div>
            <div class="card-body p-0">
                <table data-accounting-table class="table table-sm align-middle mb-0"><thead><tr><th>Cuenta</th><th>Importe</th></tr></thead><tbody>
                    <?php foreach ($statement['revenue']['accounts'] ?? [] as $a): ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format((float)($a['balance'] ?? 0), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr data-accounting-total class="table-success fw-bold"><td>Total Ingresos</td><td class="text-end"><?= number_format((float)($statement['revenue']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-warning text-dark rounded-top-4 fw-semibold">Egresos</div>
            <div class="card-body p-0">
                <table data-accounting-table class="table table-sm align-middle mb-0"><thead><tr><th>Cuenta</th><th>Importe</th></tr></thead><tbody>
                    <?php foreach ($statement['expenses']['accounts'] ?? [] as $a): ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format(abs((float)($a['balance'] ?? 0)), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr data-accounting-total class="table-warning fw-bold"><td>Total Egresos</td><td class="text-end"><?= number_format((float)($statement['expenses']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </tbody></table>
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

<?= view('accounting/end') ?>
<?= $this->endSection() ?>
