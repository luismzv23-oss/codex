<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $section = 'ledger'; ?>
<?= view('accounting/shell', get_defined_vars()) ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table data-accounting-table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Asiento</th><th>Fecha</th><th>Descripcion</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
                <?php if (empty($ledger['entries'])): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-4">Sin movimientos en el periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($ledger['entries'] as $e): ?>
                        <tr>
                            <td class="fw-semibold">#<?= esc($e['entry_number'] ?? '') ?></td>
                            <td><?= esc($e['entry_date'] ?? '') ?></td>
                            <td><?= esc($e['description'] ?? $e['entry_description'] ?? '') ?></td>
                            <td class="text-end"><?= (float)($e['debit'] ?? 0) > 0 ? number_format((float)$e['debit'], 2, ',', '.') : '' ?></td>
                            <td class="text-end"><?= (float)($e['credit'] ?? 0) > 0 ? number_format((float)$e['credit'], 2, ',', '.') : '' ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)($e['running_balance'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-accounting-total class="table-dark"><td colspan="3" class="fw-bold">Saldo del período</td><td></td><td></td><td class="text-end fw-bold"><?= number_format((float)($ledger['final_balance'] ?? 0), 2, ',', '.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= view('accounting/end') ?>
<?= $this->endSection() ?>
