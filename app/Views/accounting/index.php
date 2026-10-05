<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $section = 'index'; ?>
<?= view('accounting/shell', get_defined_vars()) ?>
<?= view('accounting/overview', get_defined_vars()) ?>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table data-accounting-table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Codigo</th><th>Cuenta</th><th>Tipo</th><th>Grupo</th><th>Nivel</th><th>Saldo Apertura</th><th class="text-end">Mayor</th>
            </tr></thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No hay cuentas cargadas. <a href="<?= site_url('contabilidad/cuentas/nueva?company_id=' . $selectedCompanyId) ?>">Crear la primera cuenta</a>.</td></tr>
                <?php else: ?>
                    <?php foreach ($accounts as $a): ?>
                        <tr class="<?= (int)($a['is_group'] ?? 0) === 1 ? 'fw-semibold' : '' ?>">
                            <td><code><?= esc($a['code']) ?></code></td>
                            <td><?= esc($a['name']) ?></td>
                            <td><span class="badge bg-light text-secondary"><?= esc(['asset'=>'Activo','liability'=>'Pasivo','equity'=>'Patrimonio','revenue'=>'Ingreso','expense'=>'Egreso'][$a['account_type'] ?? ''] ?? '') ?></span></td>
                            <td><?= (int)($a['is_group'] ?? 0) === 1 ? '<i class="bi bi-folder text-secondary"></i>' : '<i class="bi bi-file-earmark text-secondary"></i>' ?></td>
                            <td><?= esc($a['level'] ?? 1) ?></td>
                            <td><?= number_format((float)($a['opening_balance'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end">
                                <?php if ((int)($a['accepts_entries'] ?? 1) === 1): ?>
                                    <a href="<?= site_url('contabilidad/mayor/' . $a['id'] . '?' . http_build_query(['company_id'=>$selectedCompanyId] + $filters)) ?>" class="btn btn-outline-dark btn-sm icon-btn" title="Ver mayor"><i class="bi bi-list-ul"></i></a>
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
