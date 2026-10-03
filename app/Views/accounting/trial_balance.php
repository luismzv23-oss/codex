<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $section = 'trial_balance'; ?>
<?= view('accounting/shell', get_defined_vars()) ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table data-accounting-table class="table table-hover align-middle mb-0">
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
                        <td><span class="badge bg-light text-secondary"><?= esc(['asset'=>'Activo','liability'=>'Pasivo','equity'=>'Patrimonio','revenue'=>'Ingreso','expense'=>'Egreso'][$a['account_type'] ?? ''] ?? '') ?></span>
                        </td>
                        <td class="text-end"><?= number_format((float) ($a['total_debit'] ?? 0), 2, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) ($a['total_credit'] ?? 0), 2, ',', '.') ?></td>
                        <td class="text-end fw-semibold <?= (float) ($a['balance'] ?? 0) < 0 ? 'text-danger' : '' ?>">


                            <?= number_format((float) ($a['balance'] ?? 0), 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr data-accounting-total class="table-dark fw-bold">
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

<?= view('accounting/end') ?>
<?= $this->endSection() ?>
