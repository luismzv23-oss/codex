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
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Codigo</th><th>Cuenta</th><th>Tipo</th><th>Grupo</th><th>Nivel</th><th>Saldo Apertura</th><th class="text-end">Mayor</th>
            </tr></thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No hay cuentas cargadas. <a href="<?= site_url('contabilidad/cuentas/nueva') ?>">Crear la primera cuenta</a>.</td></tr>
                <?php else: ?>
                    <?php foreach ($accounts as $a): ?>
                        <tr class="<?= (int)($a['is_group'] ?? 0) === 1 ? 'fw-semibold' : '' ?>">
                            <td><code><?= esc($a['code']) ?></code></td>
                            <td><?= str_repeat('&nbsp;&nbsp;', max(0, (int)($a['level'] ?? 1) - 1)) ?><?= esc($a['name']) ?></td>
                            <td><span class="badge bg-<?= match($a['account_type'] ?? '') { 'asset' => 'primary', 'liability' => 'danger', 'equity' => 'info', 'revenue' => 'success', 'expense' => 'warning', default => 'secondary' } ?>"><?= esc(ucfirst($a['account_type'] ?? '')) ?></span></td>
                            <td><?= (int)($a['is_group'] ?? 0) === 1 ? '<i class="bi bi-folder text-warning"></i>' : '<i class="bi bi-file-earmark text-secondary"></i>' ?></td>
                            <td><?= esc($a['level'] ?? 1) ?></td>
                            <td><?= number_format((float)($a['opening_balance'] ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end">
                                <?php if ((int)($a['accepts_entries'] ?? 1) === 1): ?>
                                    <a href="<?= site_url('contabilidad/mayor/' . $a['id']) ?>" class="btn btn-outline-dark btn-sm icon-btn" title="Ver mayor"><i class="bi bi-list-ul"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
