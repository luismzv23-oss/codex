<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $section = 'balance_sheet'; ?>
<?= view('accounting/shell', get_defined_vars()) ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-primary text-white rounded-top-4 fw-semibold">Activo</div>
            <div class="card-body p-0">
                <table data-accounting-table class="table table-sm align-middle mb-0"><thead><tr><th>Cuenta</th><th>Importe</th></tr></thead><tbody>
                    <?php foreach ($balance['assets']['accounts'] ?? [] as $a): ?>
                        <?php if ((float)($a['balance'] ?? 0) == 0) continue; ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format((float)($a['balance'] ?? 0), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr data-accounting-total class="table-primary fw-bold"><td>Total Activo</td><td class="text-end"><?= number_format((float)($balance['assets']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-header bg-danger text-white rounded-top-4 fw-semibold">Pasivo</div>
            <div class="card-body p-0">
                <table data-accounting-table class="table table-sm align-middle mb-0"><thead><tr><th>Cuenta</th><th>Importe</th></tr></thead><tbody>
                    <?php foreach ($balance['liabilities']['accounts'] ?? [] as $a): ?>
                        <?php if ((float)($a['balance'] ?? 0) == 0) continue; ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format(abs((float)($a['balance'] ?? 0)), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr data-accounting-total class="table-danger fw-bold"><td>Total Pasivo</td><td class="text-end"><?= number_format((float)($balance['liabilities']['total'] ?? 0), 2, ',', '.') ?></td></tr>
                </tbody></table>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-info text-white rounded-top-4 fw-semibold">Patrimonio Neto</div>
            <div class="card-body p-0">
                <table data-accounting-table class="table table-sm align-middle mb-0"><thead><tr><th>Cuenta</th><th>Importe</th></tr></thead><tbody>
                    <?php foreach ($balance['equity']['accounts'] ?? [] as $a): ?>
                        <?php if ((float)($a['balance'] ?? 0) == 0) continue; ?>
                        <tr><td><code><?= esc($a['code'] ?? '') ?></code> <?= esc($a['name'] ?? '') ?></td><td class="text-end"><?= number_format(abs((float)($a['balance'] ?? 0)), 2, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    <tr data-accounting-total><td class="fst-italic">Resultado del ejercicio</td><td class="text-end"><?= number_format((float)($balance['equity']['net_income'] ?? 0), 2, ',', '.') ?></td></tr>
                    <tr data-accounting-total class="table-info fw-bold"><td>Total PN</td><td class="text-end"><?= number_format((float)($balance['equity']['total_with_income'] ?? 0), 2, ',', '.') ?></td></tr>
                </tbody></table>
            </div>
        </div>
    </div>
</div>
<div class="mt-3 text-center"><?= ($balance['balanced'] ?? false) ? '<span class="badge bg-success fs-6">Activo = Pasivo + PN ✓</span>' : '<span class="badge bg-danger fs-6">Desbalanceado ✗</span>' ?></div>

<?= view('accounting/end') ?>
<?= $this->endSection() ?>
