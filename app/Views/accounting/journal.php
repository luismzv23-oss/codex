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
<form class="row g-2 mb-3">
    <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= esc($filters['from'] ?? '') ?>"></div>
    <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= esc($filters['to'] ?? '') ?>"></div>
    <div class="col-auto"><select name="status" class="form-select form-select-sm"><option value="">Todos</option><option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Borrador</option><option value="posted" <?= ($filters['status'] ?? '') === 'posted' ? 'selected' : '' ?>>Contabilizado</option></select></div>
    <div class="col-auto"><button class="btn btn-outline-dark btn-sm"><i class="bi bi-search"></i></button></div>
</form>
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
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
                                    <form method="post" action="<?= site_url('contabilidad/asientos/' . $e['id'] . '/contabilizar') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-success btn-sm icon-btn" title="Contabilizar"><i class="bi bi-check-lg"></i></button></form>
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
