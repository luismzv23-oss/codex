<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $section = 'journal'; ?>
<?= view('accounting/shell', get_defined_vars()) ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table data-accounting-table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>#</th><th>Fecha</th><th>Descripcion</th><th>Origen</th><th>Debe</th><th>Haber</th><th>Estado</th><th></th>
            </tr></thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">Sin asientos en el periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($e['entry_number'] ?? '') ?></td>
                            <td><?= esc($e['entry_date'] ?? '') ?></td>
                            <td><?= esc($e['description'] ?? '') ?></td>
                            <td><span class="badge bg-secondary"><?= esc($e['reference_type'] ?? 'manual') ?></span></td>
                            <td class="text-end"><?= number_format((float)($e['total_debit'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)($e['total_credit'] ?? 0), 2, ',', '.') ?></td>
                            <td>
                                <?php if (($e['status'] ?? '') === 'posted'): ?>
                                    <span class="badge bg-success">Contabilizado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Borrador</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (($e['status'] ?? '') === 'draft'): ?>
                                    <form method="post" action="<?= site_url('contabilidad/asientos/' . $e['id'] . '/contabilizar') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="company_id" value="<?= esc($selectedCompanyId) ?>"><button class="btn btn-outline-success btn-sm icon-btn" title="Contabilizar"><i class="bi bi-check-lg"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= view('accounting/end') ?>
<?= $this->endSection() ?>
